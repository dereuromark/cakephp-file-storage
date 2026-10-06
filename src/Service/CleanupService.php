<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Closure;
use Exception;
use FileStorage\Model\Entity\FileStorage;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Storage-tree cleanup logic, shared between the `file_storage cleanup`
 * CLI command and the `Admin/FileStorage::cleanup()` action.
 *
 * Three responsibilities:
 *
 * 1. Delete `file_storage` rows whose `foreign_key` is null (orphan rows).
 * 2. Walk the configured local filesystem path and remove files that have no
 *    matching `file_storage` row (orphan files on disk).
 * 3. Report rows whose backing main/variant files are missing on the
 *    configured storage adapter (consistency check).
 *
 * Dry-run mode performs all the *queries* but skips the actual mutations.
 */
class CleanupService
{
    use LocatorAwareTrait;

    /**
     * @var string
     */
    protected const DEFAULT_BLOB_ROOT = 'blobs';

    /**
     * @var int
     */
    protected const DEFAULT_GRACE_PERIOD = 3600;

    /**
     * @var string
     */
    protected const BLOBS_TABLE = 'file_storage_blobs';

    /**
     * @var string
     */
    protected const DEFAULT_ADAPTER = 'Local';

    /**
     * @param string|null $model Optional model alias filter.
     * @param string|null $collection Optional collection filter.
     * @param bool $dryRun When true, no rows or files are actually removed.
     *
     * @return \FileStorage\Service\CleanupReport
     */
    public function run(?string $model, ?string $collection, bool $dryRun): CleanupReport
    {
        $warnings = [];
        $scopeConditions = [];
        if ($model !== null && $model !== '') {
            $scopeConditions['model'] = $model;
        }
        if ($collection !== null && $collection !== '') {
            $scopeConditions['collection'] = $collection;
        }

        // Stream the rows in two passes instead of materializing the whole
        // file_storage table into PHP memory. On deployments with hundreds of
        // thousands of attachments the previous `->all()->toArray()` would OOM.
        // Each pass is a fresh query so the underlying PDO cursor stays
        // lazy / iterable-once and we never hold all rows at the same time.
        $checkedCount = 0;
        $deletedRows = $this->removeOrphanRows($scopeConditions, $dryRun);
        $deletedFiles = $this->removeOrphanFiles(
            $this->streamScoped($scopeConditions, $checkedCount),
            $model,
            $collection,
            $dryRun,
            $warnings,
        );
        $missingFiles = $this->collectMissingFiles($this->streamScoped($scopeConditions), $warnings);

        $blobs = $this->cleanBlobs($dryRun, $warnings);

        return new CleanupReport(
            dryRun: $dryRun,
            checkedCount: $checkedCount,
            deletedFiles: $deletedFiles,
            deletedRows: $deletedRows,
            missingFiles: $missingFiles,
            warnings: $warnings,
            deletedBlobs: $blobs['deleted'],
            deletedStrayBlobs: $blobs['strays'],
            skippedBlobs: $blobs['skipped'],
        );
    }

    /**
     * Lazy iterator over file_storage rows matching $scopeConditions.
     *
     * @param array<string, mixed> $scopeConditions
     * @param int $checkedCount Pass a variable by reference only if you want
     *     row counting (it is incremented as rows flow through); otherwise
     *     omit the parameter.
     *
     * @throws \RuntimeException When the table yields a foreign entity class.
     *
     * @return iterable<\FileStorage\Model\Entity\FileStorage>
     */
    protected function streamScoped(array $scopeConditions, int &$checkedCount = 0): iterable
    {
        $table = $this->fetchTable('FileStorage.FileStorage');
        $query = $table->find()->where($scopeConditions);
        // disableBufferedResults() drops the ResultSet's internal row buffer
        // so memory stays O(1) regardless of result-set size. Trade-off: we
        // can't re-iterate the same query — every consumer here iterates
        // exactly once.
        $query->disableBufferedResults();
        foreach ($query as $image) {
            // Not an assert(): assertions are compiled out in production, so the
            // invariant would only ever fire in development. A misconfigured
            // entity class is exactly the kind of thing that must not pass here
            // silently, because everything downstream calls entity methods.
            if (!$image instanceof FileStorage) {
                throw new RuntimeException(sprintf(
                    'Expected %s, got %s. Check the entity class of your file storage table.',
                    FileStorage::class,
                    get_debug_type($image),
                ));
            }

            $checkedCount++;

            yield $image;
        }
    }

    /**
     * @param array<string, mixed> $scopeConditions
     * @param bool $dryRun
     *
     * @return int Number of orphan rows deleted (or that would be deleted).
     */
    protected function removeOrphanRows(array $scopeConditions, bool $dryRun): int
    {
        $table = $this->fetchTable('FileStorage.FileStorage');
        $conditions = $scopeConditions + ['foreign_key IS' => null];

        /** @var array<\FileStorage\Model\Entity\FileStorage> $orphans */
        $orphans = $table->find()->where($conditions)->all()->toArray();
        if ($dryRun) {
            return count($orphans);
        }

        $deleted = 0;
        foreach ($orphans as $orphan) {
            if ($table->delete($orphan)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param iterable<\FileStorage\Model\Entity\FileStorage> $images
     * @param string|null $model
     * @param string|null $collection
     * @param bool $dryRun
     * @param array<int, string> $warnings Out param.
     *
     * @return array<int, string> Absolute paths of orphan files on disk that were (or would be) deleted.
     */
    protected function removeOrphanFiles(
        iterable $images,
        ?string $model,
        ?string $collection,
        bool $dryRun,
        array &$warnings,
    ): array {
        // Hash-set keyed by absolute path for O(1) lookup; the iterator below can scan
        // many thousand files, and a linear `in_array` walk would turn that into O(n*m).
        $expected = [];
        $pathPrefix = (string)Configure::read('FileStorage.pathPrefix');
        foreach ($images as $image) {
            $expected[WWW_ROOT . $pathPrefix . $image->path] = true;
            foreach ($image->variants() as $details) {
                if (isset($details['path'])) {
                    $expected[WWW_ROOT . $pathPrefix . $details['path']] = true;
                }
            }
        }

        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($fileStorage === null) {
            $warnings[] = 'FileStorage adapter not configured, skipping orphaned file removal.';

            return [];
        }

        $path = WWW_ROOT . $pathPrefix;
        if ($model !== null && $model !== '') {
            $path .= $model . DS;
        }
        if ($collection !== null && $collection !== '') {
            $path .= $collection . DS;
        }

        if (!is_dir($path)) {
            $warnings[] = sprintf('Path does not exist or is not accessible: %s', $path);

            return [];
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $blobRoot = str_replace('\\', '/', WWW_ROOT . $pathPrefix)
            . trim((string)Configure::read('FileStorage.deduplicate.root', static::DEFAULT_BLOB_ROOT), '/\\') . '/';
        $deleted = [];
        foreach ($iter as $file) {
            $filePath = (string)$file;
            if (
                str_starts_with(str_replace('\\', '/', $filePath), $blobRoot)
                || !is_file($filePath) || array_key_exists($filePath, $expected)
            ) {
                continue;
            }

            $deleted[] = $filePath;
            if (!$dryRun) {
                @unlink($filePath);
            }
        }

        return $deleted;
    }

    /**
     * Blob sweep and stray blob files. Not scoped by model or collection,
     * because blobs are shared between them.
     *
     * @param bool $dryRun
     * @param array<int, string> $warnings Out param.
     *
     * @return array{deleted: array<int, string>, strays: array<int, string>, skipped: int}
     */
    protected function cleanBlobs(bool $dryRun, array &$warnings): array
    {
        $result = ['deleted' => [], 'strays' => [], 'skipped' => 0];
        $files = $this->fetchTable('FileStorage.FileStorage');
        $connection = $files->getConnection();
        $driver = $connection->getDriver();
        if (!$driver instanceof Mysql && !$driver instanceof Postgres && !$driver instanceof Sqlite) {
            // Deduplication cannot be in use on this database.
            return $result;
        }
        if (!in_array(static::BLOBS_TABLE, $connection->getSchemaCollection()->listTables(), true)) {
            $warnings[] = 'Table `file_storage_blobs` is missing, skipping blob cleanup. Run the plugin migrations.';

            return $result;
        }

        $adapterNames = $this->blobAdapterNames();
        $registry = new BlobRegistry($files);
        $olderThan = DateTime::now()->subSeconds(
            (int)Configure::read('FileStorage.deduplicate.gracePeriod', static::DEFAULT_GRACE_PERIOD),
        );
        $deleteFile = static function (string $name, string $path): void {
            $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
            if ($storage === null) {
                throw new RuntimeException('FileStorage adapter not configured.');
            }
            $adapter = $storage->getStorage($name);
            if ($adapter->fileExists($path)) {
                $adapter->delete($path);
            }
        };
        $sweep = $registry->sweep($olderThan, $deleteFile, $dryRun);
        $warnings = array_merge($warnings, $sweep['warnings']);
        $result['deleted'] = $sweep['deleted'];
        $result['skipped'] = $sweep['skipped'];
        $result['strays'] = $this->removeStrayBlobs(
            $adapterNames,
            $registry,
            $olderThan,
            $deleteFile,
            $dryRun,
            $warnings,
            $result['skipped'],
        );

        return $result;
    }

    /**
     * @return array<int, string>
     */
    protected function blobAdapterNames(): array
    {
        $files = $this->fetchTable('FileStorage.FileStorage');
        $blobs = $this->fetchTable('FileStorage.FileStorageBlobs');
        // The default adapter is always scanned: a first upload that rolled back
        // leaves a file there without any row naming the adapter.
        $default = Configure::read('FileStorage.behaviorConfig.defaultStorageConfig', static::DEFAULT_ADAPTER);
        $names = is_string($default) && $default !== '' ? [$default => true] : [];
        foreach ([$files, $blobs] as $table) {
            foreach ($table->find()->select(['adapter'])->distinct(['adapter']) as $row) {
                if ($row->get('adapter') !== null && $row->get('adapter') !== '') {
                    $names[(string)$row->get('adapter')] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * @param array<int, string> $adapterNames
     * @param \FileStorage\Service\BlobRegistry $registry
     * @param \Cake\I18n\DateTime $olderThan
     * @param \Closure(string, string): void $deleteFile
     * @param bool $dryRun
     * @param array<int, string> $warnings
     * @param int $skipped
     *
     * @return array<int, string>
     */
    protected function removeStrayBlobs(array $adapterNames, BlobRegistry $registry, DateTime $olderThan, Closure $deleteFile, bool $dryRun, array &$warnings, int &$skipped): array
    {
        $blobs = $this->fetchTable('FileStorage.FileStorageBlobs');
        $deleted = [];
        foreach ($adapterNames as $name) {
            try {
                $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
                if ($storage === null) {
                    throw new RuntimeException('FileStorage adapter not configured.');
                }
                $adapter = $storage->getStorage($name);
                $root = (string)Configure::read('FileStorage.deduplicate.root', static::DEFAULT_BLOB_ROOT);
                foreach ($adapter->listContents($root, true) as $file) {
                    if (!$file->isFile()) {
                        continue;
                    }
                    $path = $file->path();
                    if ($file->lastModified() === null) {
                        $warnings[] = sprintf('Unknown modification time for blob file %s on %s.', $path, $name);
                        $skipped++;

                        continue;
                    }
                    if ($file->lastModified() >= $olderThan->getTimestamp()) {
                        continue;
                    }
                    if ($blobs->exists(['adapter' => $name, 'path' => $path])) {
                        continue;
                    }
                    $hash = pathinfo($path, PATHINFO_FILENAME);
                    if (!preg_match('/^[a-f0-9]{64}$/iD', $hash)) {
                        $warnings[] = sprintf('Invalid hash in blob file %s on %s.', $path, $name);
                        $skipped++;

                        continue;
                    }
                    try {
                        if ($dryRun || $registry->removeStray($name, $hash, $path, $deleteFile)) {
                            $deleted[] = $path;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable $exception) {
                        $warnings[] = sprintf('Could not delete stray blob %s on %s: %s', $path, $name, $exception->getMessage());
                    }
                }
            } catch (Throwable $exception) {
                $warnings[] = sprintf('Could not list blobs on %s: %s', $name, $exception->getMessage());
            }
        }

        return $deleted;
    }

    /**
     * @param iterable<\FileStorage\Model\Entity\FileStorage> $images
     * @param array<int, string> $warnings Out param.
     *
     * @return array<int, array{id: string|int, missing: array<int, string>}>
     */
    protected function collectMissingFiles(iterable $images, array &$warnings): array
    {
        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($fileStorage === null) {
            $warnings[] = 'FileStorage adapter not configured, skipping existence check.';

            return [];
        }

        $missing = [];
        foreach ($images as $image) {
            if (!$image->adapter || !$image->path) {
                continue;
            }

            try {
                $adapter = $fileStorage->getStorage($image->adapter);
            } catch (Exception $e) {
                $warnings[] = sprintf('Could not get adapter for image %s: %s', $image->id, $e->getMessage());

                continue;
            }

            $missingForImage = [];
            if (!$adapter->fileExists($image->path)) {
                $missingForImage[] = 'main';
            }
            foreach ($image->variants() as $variant => $details) {
                $variantPath = $details['path'] ?? null;
                if ($variantPath && !$adapter->fileExists($variantPath)) {
                    $missingForImage[] = (string)$variant;
                }
            }

            if ($missingForImage) {
                $missing[] = ['id' => $image->id, 'missing' => $missingForImage];
            }
        }

        return $missing;
    }
}
