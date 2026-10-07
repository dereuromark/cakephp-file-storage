<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use FileStorage\Model\Entity\FileStorage;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use PhpCollective\Infrastructure\Storage\File;
use PhpCollective\Infrastructure\Storage\FileStorage as Storage;
use RuntimeException;
use Throwable;

/**
 * Converts legacy rows while preserving their source files.
 *
 * @author Mark Scherer
 * @license MIT
 */
class ExistingFileDeduplicator
{
    use LocatorAwareTrait;

    /**
     * @var string
     */
    public const SKIP_CHANGED = 'row changed';

    /**
     * @var string
     */
    public const SKIP_NOT_ENABLED = 'collection not opted in';

    /**
     * @var string
     */
    public const SKIP_BLOB_ROOT = 'path under blob root';

    /**
     * @var int
     */
    public const BATCH_SIZE = 100;

    /**
     * @var int
     */
    protected const SAMPLE_LIMIT = 50;

    /**
     * @var int
     */
    private const CHUNK_SIZE = 65536;

    /**
     * @var int
     */
    protected const HASH_COLUMN_LENGTH = 64;

    /**
     * @var array<string, int>
     */
    protected array $counts = [];

    /**
     * @var array<string, int>
     */
    protected array $skipped = [];

    /**
     * @var array<string, array<int, string>>
     */
    protected array $samples = [];

    protected string $root;

    protected string $algorithm;

    protected Storage $storage;

    /**
     * @param string|null $model
     * @param string|null $collection
     * @param array{dryRun?: bool, hashOnly?: bool, limit?: int|null, manifest?: string|null} $options
     *
     * @throws \RuntimeException
     *
     * @return \FileStorage\Service\ExistingFileReport
     */
    public function run(?string $model = null, ?string $collection = null, array $options = []): ExistingFileReport
    {
        $this->counts = [];
        $this->skipped = [];
        $this->samples = [];
        $dryRun = $options['dryRun'] ?? false;
        $hashOnly = $options['hashOnly'] ?? false;
        $limit = $options['limit'] ?? null;
        if ($limit !== null && $limit < 1) {
            throw new RuntimeException('Limit must be a positive integer.');
        }
        $this->preconditions($hashOnly);
        $table = $this->fetchTable('FileStorage.FileStorage');
        if ($table->getConnection()->inTransaction()) {
            throw new RuntimeException('Deduplication requires a connection outside a transaction.');
        }
        if (!$hashOnly) {
            $reserved = $table->find()->where(['blob_id IS' => null])
                ->where(fn ($exp, $query) => $exp->eq($query->func()->substr(['path' => 'identifier', 1, strlen($this->root) + 1], [1 => 'integer', 2 => 'integer'], 'string'), $this->root . '/', 'string'))->count();
            if ($reserved > 0) {
                $this->detail('warnings', sprintf('%d rows are not converted because the blob directory is reserved for blobs.', $reserved));
            }
        }
        $manifest = null;
        if (!$dryRun && ($options['manifest'] ?? null) !== null) {
            $manifest = @fopen($options['manifest'], 'ab');
            if ($manifest === false) {
                throw new RuntimeException('Could not open manifest for append.');
            }
        }
        try {
            $query = $this->selection($model, $collection, $hashOnly);
            $high = $table->find()->select(['id'])->orderBy(['id' => 'DESC'])->first()?->get('id');
            if ($high !== null) {
                $query->where(['id <=' => $high]);
                if ($dryRun) {
                    $this->preview($query, $hashOnly, $limit);
                } else {
                    $attempted = 0;
                    foreach ($this->rows($query) as $row) {
                        if ($limit !== null && $attempted >= $limit) {
                            break;
                        }
                        $attempted++;
                        if (!$hashOnly && $this->skip($row)) {
                            continue;
                        }
                        $this->process($row, $hashOnly, $manifest);
                    }
                }
            }
        } finally {
            if (is_resource($manifest)) {
                fclose($manifest);
            }
        }

        return new ExistingFileReport(
            dryRun: $dryRun,
            hashed: $this->counts['hashed'] ?? 0,
            converted: $this->counts['converted'] ?? 0,
            reusedExisting: $this->counts['reusedExisting'] ?? 0,
            oldFilesKept: $this->counts['oldFilesKept'] ?? 0,
            bytesKept: $this->counts['bytesKept'] ?? 0,
            skipped: $this->skipped,
            missingFiles: $this->counts['missingSamples'] ?? 0,
            missingSamples: $this->samples['missingSamples'] ?? [],
            failures: $this->counts['failureSamples'] ?? 0,
            failureSamples: $this->samples['failureSamples'] ?? [],
            warnings: $this->samples['warnings'] ?? [],
            warningCount: $this->counts['warnings'] ?? 0,
            candidates: $this->counts['candidates'] ?? 0,
            candidateBytes: $this->counts['candidateBytes'] ?? 0,
            withoutHash: $this->counts['withoutHash'] ?? 0,
            estimatedDuplicates: $this->counts['estimatedDuplicates'] ?? 0,
        );
    }

    protected function preconditions(bool $hashOnly): void
    {
        $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if (!$storage instanceof Storage) {
            throw new RuntimeException('FileStorage adapter service is not configured.');
        }
        $this->storage = $storage;
        $algorithm = Configure::read('FileStorage.hashAlgorithm', 'sha256');
        if (!is_string($algorithm) || !in_array($algorithm, hash_algos(), true)) {
            throw new RuntimeException('Invalid FileStorage.hashAlgorithm.');
        }
        if (strlen(hash($algorithm, '')) > static::HASH_COLUMN_LENGTH) {
            throw new RuntimeException('The hashAlgorithm digest does not fit the hash column.');
        }
        $this->algorithm = $algorithm;
        $this->root = trim(str_replace('\\', '/', (string)Configure::read('FileStorage.deduplicate.root', 'blobs')), '/');
        if ($this->root === '' || preg_match('#(^|/)\.\.?(/|$)#', $this->root)) {
            throw new RuntimeException('Invalid FileStorage.deduplicate.root.');
        }
        if ($hashOnly) {
            return;
        }
        $connection = $this->fetchTable('FileStorage.FileStorage')->getConnection();
        $driver = $connection->getDriver();
        if (!$driver instanceof Mysql && !$driver instanceof Postgres && !$driver instanceof Sqlite) {
            throw new RuntimeException('Unsupported deduplication database driver.');
        }
        if (!in_array('file_storage_blobs', $connection->getSchemaCollection()->listTables(), true)) {
            throw new RuntimeException('The file_storage_blobs table is missing.');
        }
        if ($algorithm !== 'sha256') {
            throw new RuntimeException('Deduplication requires hashAlgorithm sha256.');
        }
        if ($driver instanceof Sqlite) {
            $this->detail('warnings', 'SQLite requires exclusive database use while deduplication runs.');
        }
    }

    /**
     * @return \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface>
     */
    protected function selection(?string $model, ?string $collection, bool $hashOnly): SelectQuery
    {
        $conditions = ['path IS NOT' => null, 'path !=' => '', 'adapter IS NOT' => null, 'adapter !=' => ''];
        if ($model !== null && $model !== '') {
            $conditions['model'] = $model;
        }
        if ($collection !== null && $collection !== '') {
            $conditions['collection'] = $collection;
        }
        $query = $this->fetchTable('FileStorage.FileStorage')->find()->where($conditions);
        if ($hashOnly) {
            $query->where(['OR' => ['hash IS' => null, 'hash' => '']]);
        } else {
            $query->where(['blob_id IS' => null]);
        }

        return $query;
    }

    /**
     * @param \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface> $query
     *
     * @throws \RuntimeException
     *
     * @return iterable<\FileStorage\Model\Entity\FileStorage>
     */
    protected function rows(SelectQuery $query): iterable
    {
        $cursor = null;
        do {
            $batch = clone $query;
            if ($cursor !== null) {
                $batch->where(['id >' => $cursor]);
            }
            $rows = $batch->orderBy(['id' => 'ASC'])->limit(static::BATCH_SIZE)->all()->toArray();
            foreach ($rows as $row) {
                if (!$row instanceof FileStorage) {
                    throw new RuntimeException('Expected a FileStorage entity.');
                }
                $cursor = $row->id;

                yield $row;
            }
            $batchCount = count($rows);
        } while ($batchCount === static::BATCH_SIZE);
    }

    protected function skip(FileStorage $row): bool
    {
        $reason = null;
        if ($this->underRoot((string)$row->path)) {
            $reason = static::SKIP_BLOB_ROOT;
        } elseif (!DeduplicationConfig::isEnabled($row->model, $row->collection)) {
            $reason = static::SKIP_NOT_ENABLED;
        }
        if ($reason === null) {
            return false;
        }
        $this->skipped[$reason] = ($this->skipped[$reason] ?? 0) + 1;

        return true;
    }

    /**
     * @param \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface> $query
     * @param int|null $limit
     * @param bool $hashOnly
     */
    protected function preview(SelectQuery $query, bool $hashOnly, ?int $limit): void
    {
        $candidates = clone $query;
        if ($limit !== null) {
            $last = (clone $query)->select(['id'])->orderBy(['id' => 'ASC'])->limit(1)->offset($limit - 1)->first();
            if ($last !== null) {
                $candidates->where(['id <=' => $last->get('id')]);
            }
        }
        // Configuration is PHP data; filter opted-in scopes before SQL aggregation.
        $scopes = [];
        $configured = Configure::read('FileStorage.deduplicate.collections', false);
        if (is_array($configured)) {
            foreach ($configured as $model => $collections) {
                if ($collections === true) {
                    $scopes[] = ['model' => $model];
                } elseif (is_array($collections)) {
                    foreach ($collections as $collection => $enabled) {
                        if ($enabled === true) {
                            $scopes[] = ['model' => $model, 'collection' => $collection];
                        }
                    }
                }
            }
        }
        foreach ($this->rows($candidates) as $row) {
            if (!$hashOnly && $this->skip($row)) {
                continue;
            }
            $this->increment('candidates');
            $this->increment('candidateBytes', (int)$row->filesize);
            if ($row->hash === null || $row->hash === '') {
                $this->increment('withoutHash');
            }
        }
        if ($hashOnly || ($configured !== true && $scopes === [])) {
            return;
        }
        $hashed = (clone $candidates)
            ->where(['hash IS NOT' => null, 'hash !=' => ''])
            ->where(fn ($exp, $query) => $exp->notEq($query->func()->substr(['path' => 'identifier', 1, strlen($this->root) + 1], [1 => 'integer', 2 => 'integer'], 'string'), $this->root . '/', 'string'));
        if ($configured !== true) {
            $hashed->where(['OR' => $scopes]);
        }
        // Three aggregates instead of a loop over the groups: a table with millions
        // of distinct files must not be walked in PHP, and MySQL does not allow
        // another query while an unbuffered one is open.
        $rowsWithHash = (clone $hashed)->count();
        $table = $this->fetchTable('FileStorage.FileStorage');
        $groups = (clone $hashed)->select(['adapter', 'hash'], true)->groupBy(['adapter', 'hash']);
        $distinct = (int)$table->getConnection()->selectQuery()
            ->select(['total' => $groups->func()->count('*')])
            ->from(['hash_groups' => $groups])
            ->execute()
            ->fetchColumn(0);
        $blobs = $this->fetchTable('FileStorage.FileStorageBlobs');
        $matching = (clone $hashed)->select(['id'], true)->where(fn ($exp) => $exp
            ->equalFields($table->aliasField('adapter'), $blobs->aliasField('adapter'))
            ->equalFields($table->aliasField('hash'), $blobs->aliasField('hash')));
        $withBlob = $blobs->find()->where(fn ($exp) => $exp->exists($matching))->count();
        // Every group keeps one file, unless a blob for it exists already.
        $this->increment('estimatedDuplicates', $rowsWithHash - $distinct + $withBlob);
    }

    /**
     * @param \FileStorage\Model\Entity\FileStorage $listed
     * @param bool $hashOnly
     * @param resource|null $manifest
     */
    protected function process(FileStorage $listed, bool $hashOnly, $manifest): void
    {
        $table = $this->fetchTable('FileStorage.FileStorage');
        $connection = $table->getConnection();
        $local = null;
        $adapter = null;
        $temporary = null;
        $destination = null;
        $createdDestination = false;
        $configurationError = false;
        $committed = false;
        $reused = false;
        $bytes = 0;
        $hash = '';
        try {
            if (!$hashOnly) {
                $connection->begin();
                $query = $table->find()->where(['id' => $listed->id]);
                $driver = $connection->getDriver();
                if ($driver instanceof Mysql || $driver instanceof Postgres) {
                    $query->epilog('FOR UPDATE');
                }
                $row = $query->first();
                if (!$row instanceof FileStorage || $row->path !== $listed->path || $row->adapter !== $listed->adapter || $row->blob_id !== null) {
                    $this->skipped[static::SKIP_CHANGED] = ($this->skipped[static::SKIP_CHANGED] ?? 0) + 1;
                    $connection->rollback();

                    return;
                }
                if ($this->skip($row)) {
                    $connection->rollback();

                    return;
                }
            } else {
                $row = $listed;
            }
            $adapter = $this->storage->getStorage((string)$row->adapter);
            if (!$adapter->fileExists((string)$row->path)) {
                $this->detail('missingSamples', sprintf('ID %s: %s', $row->id, $row->path));
                if (!$hashOnly) {
                    $connection->rollback();
                }

                return;
            }
            $local = tempnam(sys_get_temp_dir(), 'fsdedup');
            if ($local === false) {
                throw new RuntimeException('Could not create local temporary copy.');
            }
            [$hash, $bytes] = $this->readSource($adapter, (string)$row->path, $local);
            if ($bytes !== (int)$row->filesize) {
                $this->detail('warnings', sprintf('ID %s filesize differs: stored %d, read %d.', $row->id, $row->filesize, $bytes));
            }
            $conditions = ['id' => $row->id, 'path' => $row->path, 'adapter' => $row->adapter];
            if ($hashOnly) {
                $table->updateAll(['hash' => $hash], $conditions + ['OR' => ['hash IS' => null, 'hash' => '']]);
                $fresh = $table->find()->where($conditions + ['hash' => $hash])->first();
                if ($fresh === null) {
                    $this->skipped[static::SKIP_CHANGED] = ($this->skipped[static::SKIP_CHANGED] ?? 0) + 1;
                } else {
                    $this->increment('hashed');
                }

                return;
            }
            $registry = new BlobRegistry($table);
            $claim = $registry->withLockWaitLimit(fn () => $registry->claim((string)$row->adapter, $hash, DateTime::now()));
            $destination = $claim->path;
            if ($destination === null) {
                $file = File::create((string)$row->filename, $bytes, (string)$row->mime_type, (string)$row->adapter, $row->collection, $row->model, $row->foreign_key === null ? null : (string)$row->foreign_key);
                $file = $file->withUuid((string)$row->uuid)->withHash($hash);
                $configurationError = true;
                $destination = $this->storage->buildPath($file)->path();
                if (!$this->validDestination($destination, $hash)) {
                    throw new InvalidArgumentException('hashPathTemplate must name the file by its hash under the blob root, outside .tmp.');
                }
                $configurationError = false;
            } elseif (!$this->validDestination($destination, $hash)) {
                throw new RuntimeException('Registered blob path is invalid.');
            }
            if ($destination === $row->path) {
                throw new RuntimeException('Destination equals source path.');
            }
            if ($adapter->fileExists($destination)) {
                if ($this->hashStored($adapter, $destination) !== $hash) {
                    throw new RuntimeException($claim->path === null ? 'destination occupied' : 'registered blob has wrong content');
                }
                $reused = true;
            } else {
                $temporary = $this->root . '/.tmp/' . $hash . '.' . bin2hex(random_bytes(8)) . '.part';
                $stream = fopen($local, 'rb');
                if ($stream === false) {
                    throw new RuntimeException('Could not open local copy.');
                }
                try {
                    $adapter->writeStream($temporary, $stream, new Config());
                } finally {
                    fclose($stream);
                }
                if ($this->hashStored($adapter, $temporary) !== $hash) {
                    throw new RuntimeException('Temporary blob read-back mismatch.');
                }
                // Recheck before move so a newly occupied destination is preserved.
                if ($adapter->fileExists($destination)) {
                    throw new RuntimeException('destination occupied');
                }
                // Also when the path is already registered: the file was missing, and
                // a wrong one left behind would be accepted by the next upload.
                $createdDestination = true;
                $adapter->move($temporary, $destination, new Config());
                if ($this->hashStored($adapter, $destination) !== $hash) {
                    throw new RuntimeException('Destination read-back mismatch.');
                }
            }
            if ($claim->path === null) {
                $registry->recordPath($claim->id, $destination);
            }
            $fields = ['path' => $destination, 'blob_id' => $claim->id, 'hash' => $hash];
            $table->updateAll($fields, $conditions + ['blob_id IS' => null]);
            if (!$table->exists(['id' => $row->id, 'adapter' => $row->adapter] + $fields)) {
                throw new RuntimeException('Converted row could not be read back.');
            }
            $connection->commit();
            $committed = true;
        } catch (Throwable $exception) {
            if (!$hashOnly && $connection->inTransaction()) {
                // Remove uncommitted output while the blob lock is still held.
                if ($createdDestination && $adapter !== null && $destination !== null) {
                    try {
                        $adapter->delete($destination);
                    } catch (Throwable $cleanupError) {
                        $this->detail('warnings', 'Could not remove uncommitted destination: ' . $cleanupError->getMessage());
                    }
                }
                $connection->rollback();
            }
            if ($configurationError) {
                throw new RuntimeException('Invalid hashPathTemplate: ' . $exception->getMessage(), 0, $exception);
            }
            $this->detail('failureSamples', sprintf('ID %s: %s', $listed->id, $exception->getMessage()));
        } finally {
            if ($temporary !== null && $adapter !== null) {
                try {
                    if ($adapter->fileExists($temporary)) {
                        $adapter->delete($temporary);
                    }
                } catch (Throwable $exception) {
                    $this->detail('warnings', 'Could not remove temporary blob: ' . $exception->getMessage());
                }
            }
            if (is_string($local)) {
                unlink($local);
            }
        }
        if (!$committed) {
            return;
        }
        $this->increment('converted');
        $this->increment('oldFilesKept');
        $this->increment('bytesKept', $bytes);
        if ($reused) {
            $this->increment('reusedExisting');
        }
        try {
            $table->dispatchEvent('FileStorage.blobConverted', ['id' => $listed->id, 'oldPath' => $listed->path, 'path' => $destination, 'adapter' => $listed->adapter, 'hash' => $hash]);
        } catch (Throwable $exception) {
            $this->detail('warnings', sprintf('ID %s converted; event listener failed: %s', $listed->id, $exception->getMessage()));
        }
        if (is_resource($manifest)) {
            $line = implode("\t", [(string)$listed->id, (string)$listed->adapter, (string)$listed->path, (string)$destination, $hash]) . "\n";
            if (fwrite($manifest, $line) !== strlen($line) || !fflush($manifest)) {
                throw new RuntimeException(sprintf('Conversion of ID %s committed, but manifest write failed; old path: %s.', $listed->id, $listed->path));
            }
        }
    }

    /**
     * @throws \RuntimeException
     *
     * @return array{string, int}
     */
    protected function readSource(FilesystemAdapter $adapter, string $path, string $local): array
    {
        $size = $adapter->fileSize($path)->fileSize();
        $source = $adapter->readStream($path);
        $copy = null;
        try {
            $copy = fopen($local, 'wb');
            if (!is_resource($source) || $copy === false) {
                throw new RuntimeException('Could not open source or temporary stream.');
            }
            $context = hash_init($this->algorithm);
            $bytes = 0;
            while (!feof($source)) {
                $chunk = fread($source, self::CHUNK_SIZE);
                if ($chunk === false || ($chunk === '' && !feof($source))) {
                    throw new RuntimeException('Source read failed.');
                }
                if (fwrite($copy, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Local copy write failed.');
                }
                hash_update($context, $chunk);
                $bytes += strlen($chunk);
            }
            if ($bytes !== $size) {
                throw new RuntimeException('Adapter size differs from bytes read.');
            }

            return [hash_final($context), $bytes];
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($copy)) {
                fclose($copy);
            }
        }
    }

    protected function hashStored(FilesystemAdapter $adapter, string $path): string
    {
        return BlobStream::hash($adapter, $path);
    }

    protected function underRoot(string $path): bool
    {
        return str_starts_with(str_replace('\\', '/', $path), $this->root . '/');
    }

    protected function validDestination(string $path, string $hash): bool
    {
        return BlobPath::check($path, $this->root, $hash, true) === null;
    }

    protected function increment(string $name, int $amount = 1): void
    {
        $this->counts[$name] = ($this->counts[$name] ?? 0) + $amount;
    }

    protected function detail(string $name, string $message): void
    {
        $this->increment($name);
        if (count($this->samples[$name] ?? []) < static::SAMPLE_LIMIT) {
            $this->samples[$name][] = $message;
        }
    }
}
