<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Closure;
use FileStorage\Exception\UploadDeniedException;
use FileStorage\Exception\UploadException;
use FileStorage\Exception\UploadInTransactionException;
use FileStorage\Exception\UploadInvalidException;
use FileStorage\Exception\UploadLockedException;
use FileStorage\Exception\UploadNotCompleteException;
use FileStorage\Exception\UploadNotFoundException;
use FileStorage\Http\CompletedUpload;
use HashContext;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Durable local resumable upload sessions.
 *
 * @author Mark Scherer
 * @license MIT
 */
class ResumableUploads
{
    protected Table $files;

    protected Table $uploads;

    protected Closure $clock;

    public function __construct(?Table $files = null, ?Table $uploads = null, ?Closure $clock = null)
    {
        $this->files = $files ?? TableRegistry::getTableLocator()->get('FileStorage.FileStorage');
        $this->uploads = $uploads ?? new Table(['table' => 'file_storage_uploads', 'connection' => $this->files->getConnection()]);
        if ($this->uploads->getConnection() !== $this->files->getConnection()) {
            throw new RuntimeException('Upload and file tables must share a connection.');
        }
        $driver = $this->files->getConnection()->getDriver();
        if (!$driver instanceof Mysql && !$driver instanceof Postgres && !$driver instanceof Sqlite) {
            throw new RuntimeException('Unsupported upload database driver.');
        }
        $this->clock = $clock ?? static fn (): DateTime => DateTime::now();
    }

    public function limit(string $key): int
    {
        $defaults = [
            'maxSize' => 5 * 1024 ** 3,
            'maxBytesPerOwner' => 10 * 1024 ** 3,
            'maxSessions' => 10,
            'maxReservedBytes' => 50 * 1024 ** 3,
            'minFreeBytes' => 1024 ** 3,
            'expires' => 86400,
            'completedExpires' => 86400,
        ];

        return (int)Configure::read('FileStorage.resumable.' . $key, $defaults[$key]);
    }

    /**
     * @param string|null $raw
     *
     * @throws \FileStorage\Exception\UploadInvalidException
     *
     * @return array<string, string>
     */
    public static function metadata(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (strlen($raw) > 4096) {
            throw new UploadInvalidException('Metadata exceeds 4096 bytes.');
        }
        $result = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if (!preg_match('/^([^ ,]+)(?: ([A-Za-z0-9+\/]*={0,2}))?$/D', $pair, $matches) || array_key_exists($matches[1], $result)) {
                throw new UploadInvalidException('Invalid or duplicate metadata key.');
            }
            $value = base64_decode($matches[2] ?? '', true);
            if ($value === false) {
                throw new UploadInvalidException('Invalid metadata encoding.');
            }
            $result[$matches[1]] = $value;
        }
        foreach (['filename', 'filetype', 'model', 'collection', 'sha256'] as $key) {
            if (isset($result[$key]) && (preg_match('//u', $result[$key]) !== 1 || preg_match('/\p{Cc}/u', $result[$key]))) {
                throw new UploadInvalidException('Invalid metadata text.');
            }
        }
        if (isset($result['filename']) && (strlen($result['filename']) > 255 || in_array($result['filename'], ['.', '..'], true) || strpbrk($result['filename'], '/\\') !== false)) {
            throw new UploadInvalidException('Filename must be a basename.');
        }
        foreach (['model' => '/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)*$/D', 'collection' => '/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', 'sha256' => '/^[a-f0-9]{64}$/D'] as $key => $pattern) {
            if (isset($result[$key]) && (strlen($result[$key]) > 255 || !preg_match($pattern, $result[$key]))) {
                throw new UploadInvalidException('Invalid ' . $key . ' metadata.');
            }
        }

        return $result;
    }

    /**
     * @param int $size
     * @param string|null $metadata
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function create(int $size, ?string $metadata = null, array $context = []): array
    {
        $this->noTransaction();
        $parsed = self::metadata($metadata);
        if ($size < 0 || !isset($parsed['model'])) {
            throw new UploadInvalidException('A length and model are required.');
        }
        if ($this->limit('maxSize') && $size > $this->limit('maxSize')) {
            throw new UploadException('Upload exceeds maxSize.', 413);
        }
        $row = ['size' => $size, 'model' => $parsed['model'], 'collection' => $parsed['collection'] ?? null, 'filename' => $parsed['filename'] ?? null];
        $owner = $this->authorize('create', $row, $context);
        $digest = hash('sha256', '');
        if ($size === 0 && isset($parsed['sha256']) && $parsed['sha256'] !== $digest) {
            throw new UploadException('Declared digest does not match.', 422);
        }
        $this->directory();
        $admission = $this->openAdmission();
        $part = null;
        $path = null;
        try {
            if (!flock($admission, LOCK_EX)) {
                throw new UploadException('Admission lock failed.');
            }
            $owned = $this->uploads->find()->where(['user_id' => $owner, 'state !=' => 'consumed']);
            $count = 0;
            $bytes = 0;
            foreach ($owned as $session) {
                $count++;
                $bytes += (int)$session->get('size');
            }
            if ($count >= $this->limit('maxSessions') || $bytes > $this->limit('maxBytesPerOwner') - $size) {
                throw new UploadDeniedException('Owner quota exceeded.');
            }
            $reserved = 0;
            $remaining = 0;
            foreach ($this->uploads->find() as $session) {
                if ($this->partExists($this->partPath($session->get('id')))) {
                    $reserved += (int)$session->get('size');
                }
                if ($session->get('state') === 'uploading') {
                    $remaining += (int)$session->get('size') - (int)$session->get('upload_offset');
                }
            }
            $free = disk_free_space($this->directoryPath());
            if ($reserved > $this->limit('maxReservedBytes') - $size || $free === false || $free - $remaining - $size < $this->limit('minFreeBytes')) {
                throw new UploadException('Disk reservation exceeded.', 507);
            }
            $row += [
                'id' => Text::uuid(),
                'user_id' => $owner,
                'upload_offset' => 0,
                'revision' => 0,
                'declared_hash' => $parsed['sha256'] ?? null,
                'metadata' => $metadata,
                'state' => $size === 0 ? 'complete' : 'uploading',
                'hash' => $size === 0 ? $digest : null,
                'hash_state' => $size === 0 ? null : base64_encode(serialize(hash_init('sha256'))),
                'file_storage_id' => null,
                'created' => ($this->clock)(),
                'modified' => ($this->clock)(),
                'expires' => $this->deadline($size === 0),
            ];
            $path = $this->partPath($row['id']);
            $part = @fopen($path, 'xb');
            if ($part === false || !chmod($path, 0600) || !fsync($part)) {
                throw new UploadException('Cannot create durable part file.');
            }
            $this->syncDirectory($this->directoryPath());
            $query = $this->uploads->insertQuery()->insert(array_keys($row))->values($row);
            $this->uploads->getConnection()->getDriver()->quoter()->quote($query)->execute();
        } catch (Throwable $error) {
            if ($path !== null && is_resource($part)) {
                @unlink($path);
            }

            throw $error;
        } finally {
            if (is_resource($part)) {
                fclose($part);
            }
            fclose($admission);
        }
        if ($size === 0) {
            $this->completed($row);
        }

        return $row;
    }

    /**
     * @param string $id
     * @param array<string, mixed> $context
     * @param string $action
     *
     * @throws \FileStorage\Exception\UploadException
     *
     * @return array<string, mixed>
     */
    public function offset(string $id, array $context = [], string $action = 'read'): array
    {
        $row = $this->checked($id, $action, $context);
        try {
            if ($action === 'read' && $row['state'] !== 'consumed' && !$this->partExists($this->partPath($id))) {
                $row = $this->checked($id, $action, $context);
                if ($row['state'] !== 'consumed') {
                    throw new UploadException('Part file is missing.', 410, $row);
                }
            }
        } catch (UploadException $error) {
            throw new ($error::class)($error->getMessage(), $error->status, $error->upload ?? $row, $error->entity);
        }

        return $row;
    }

    /**
     * @param string $id
     * @param int $offset
     * @param \Psr\Http\Message\StreamInterface|resource $body
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function append(string $id, int $offset, mixed $body, array $context = []): array
    {
        $this->noTransaction();
        [$row, $handle] = $this->locked($id, 'write', $context);
        $completed = false;
        try {
            if ($offset < 0) {
                throw new UploadInvalidException('Offset must be nonnegative.', 400, $row);
            }
            if ($offset !== (int)$row['upload_offset']) {
                throw new UploadException('Offset does not match.', 409, $row);
            }
            if ($row['state'] !== 'uploading') {
                if ($this->read($body) !== '') {
                    throw new UploadInvalidException('Completed upload cannot accept bytes.', 400, $row);
                }
                if ($row['state'] === 'complete') {
                    $this->update($row, []);
                    $row['revision']++;
                }

                return $row;
            }
            if (!is_resource($handle)) {
                throw new UploadException('Part file is missing.', 410, $row);
            }
            $stat = fstat($handle);
            if ($stat === false) {
                throw new UploadException('Cannot stat part.', 500, $row);
            }
            if ($stat['size'] < $offset) {
                $this->discard($row);

                throw new UploadException('Committed bytes are missing.', 410, $row);
            }
            if ($stat['size'] > $offset && !$this->truncatePart($handle, $offset)) {
                throw new UploadException('Cannot truncate part.', 500, $row);
            }
            if (fseek($handle, $offset) !== 0) {
                throw new UploadException('Cannot seek part.', 500, $row);
            }
            try {
                $encoded = base64_decode((string)$row['hash_state'], true);
                $hash = $encoded === false ? null : @unserialize($encoded, ['allowed_classes' => ['HashContext']]);
                if (!$hash instanceof HashContext) {
                    throw new RuntimeException('Invalid hash state.');
                }
            } catch (Throwable) {
                $this->discard($row);

                throw new UploadException('Hash state is corrupt.', 410, $row);
            }
            $position = $offset;
            while (($block = $this->read($body)) !== '') {
                if (strlen($block) > (int)$row['size'] - $position) {
                    throw new UploadInvalidException('Body exceeds declared length.', 400, $row);
                }
                $written = 0;
                $blockLength = strlen($block);
                while ($written < $blockLength) {
                    $amount = $this->writePart($handle, substr($block, $written));
                    if ($amount === false || $amount === 0) {
                        throw new UploadException('Cannot write part.', 500, $row);
                    }
                    hash_update($hash, substr($block, $written, $amount));
                    $written += $amount;
                }
                $position += $written;
            }
            if (!$this->syncPart($handle)) {
                throw new UploadException('Cannot sync part.', 500, $row);
            }
            $completed = $position === (int)$row['size'];
            $changes = ['upload_offset' => $position, 'expires' => $this->deadline($completed)];
            if ($completed) {
                $digest = hash_final($hash);
                if ($row['declared_hash'] !== null && $row['declared_hash'] !== $digest) {
                    $this->discard($row);

                    throw new UploadException('Declared digest does not match.', 422, $row);
                }
                $changes += ['hash' => $digest, 'hash_state' => null, 'state' => 'complete'];
            } else {
                $changes['hash_state'] = base64_encode(serialize($hash));
            }
            $this->update($row, $changes);
            $row = array_replace($row, $changes);
            $row['revision']++;
        } catch (UploadException $error) {
            throw new ($error::class)($error->getMessage(), $error->status, $error->upload ?? $row, $error->entity);
        } catch (Throwable $error) {
            throw new UploadException($error->getMessage(), 500, $row);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        if ($completed) {
            $this->completed($row);
        }

        return $row;
    }

    /**
     * @param string $id
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function terminate(string $id, array $context = []): array
    {
        $this->noTransaction();
        [$row, $handle] = $this->locked($id, 'delete', $context);
        try {
            $this->discard($row);
        } catch (UploadException $error) {
            throw new ($error::class)($error->getMessage(), $error->status, $error->upload ?? $row, $error->entity);
        } catch (Throwable $error) {
            throw new UploadException($error->getMessage(), 500, $row);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $row;
    }

    /**
     * @param string $id
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function consume(string $id, array $data, array $context = []): EntityInterface
    {
        $this->noTransaction();
        [$row, $handle] = $this->locked($id, 'consume', $context, $data);
        try {
            if ($row['state'] !== 'complete') {
                throw new UploadNotCompleteException('Upload is not complete.', 409, $row);
            }
            $reserved = ['file', 'filename', 'model', 'collection', 'hash', 'path', 'adapter', 'blob_id', 'uuid', 'filesize', 'mime_type', 'extension', 'variants'];
            if (array_intersect($reserved, array_keys($data))) {
                throw new UploadInvalidException('Consume data contains reserved fields.', 400, $row);
            }
            $parsed = self::metadata($row['metadata']);
            $file = new CompletedUpload($this->partPath($id), (int)$row['size'], $row['filename'], $parsed['filetype'] ?? null, $row['hash']);
            $entity = $this->files->getConnection()->transactional(function () use ($row, $data, $file): EntityInterface {
                $entity = $this->files->newEntity(['file' => $file, 'model' => $row['model'], 'collection' => $row['collection'], 'filename' => $row['filename']] + $data);
                try {
                    $this->files->saveOrFail($entity);
                } catch (Throwable $error) {
                    throw new UploadInvalidException($error->getMessage(), 400, $row, $entity);
                }
                $this->update($row, ['state' => 'consumed', 'file_storage_id' => $entity->get('id'), 'expires' => $this->deadline(true)]);

                return $entity;
            });
            // A failed unlink leaves a counted reservation for termination or cleanup.
            @unlink($this->partPath($id));

            return $entity;
        } catch (UploadException $error) {
            throw new ($error::class)($error->getMessage(), $error->status, $error->upload ?? $row, $error->entity);
        } catch (Throwable $error) {
            throw new UploadException($error->getMessage(), 500, $row);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * @param bool $dryRun
     *
     * @return array{deletedUploads: int, deletedUploadParts: int, skippedUploads: int}
     */
    public function cleanup(bool $dryRun = false): array
    {
        $this->noTransaction();
        $now = ($this->clock)();
        $result = ['deletedUploads' => 0, 'deletedUploadParts' => 0, 'skippedUploads' => 0];
        $ids = $this->uploads->find()->select(['id'])->where(['expires <' => $now])->all()->extract('id')->toList();
        foreach (glob($this->directoryPath() . '/*.part') ?: [] as $path) {
            $id = basename($path, '.part');
            if (is_link($path) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di', $id)) {
                $result['skippedUploads']++;

                continue;
            }
            if (!$this->row($id) && filemtime($path) < $now->getTimestamp() - $this->limit('expires')) {
                $ids[] = $id;
            }
        }
        foreach (array_unique($ids) as $id) {
            $handle = null;
            try {
                $path = $this->partPath($id);
                $handle = $this->openPart($path);
                if (is_resource($handle) && !flock($handle, LOCK_EX | LOCK_NB)) {
                    throw new UploadLockedException('Upload is locked.');
                }
                $row = $this->row($id);
                if ($row && $row['expires'] >= $now) {
                    $result['skippedUploads']++;

                    continue;
                }
                if (!$row && (!is_resource($handle) || filemtime($path) >= $now->getTimestamp() - $this->limit('expires'))) {
                    $result['skippedUploads']++;

                    continue;
                }
                if ($this->partExists($path)) {
                    if (!$dryRun && !$this->unlinkPart($path)) {
                        throw new UploadException('Cannot remove part.');
                    }
                    $result['deletedUploadParts']++;
                }
                if ($row) {
                    if (!$dryRun) {
                        $this->delete($row, $now);
                    }
                    $result['deletedUploads']++;
                }
            } catch (UploadException) {
                $result['skippedUploads']++;
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }

        return $result;
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>|null
     */
    protected function row(string $id): ?array
    {
        $this->partPath($id);
        $row = $this->uploads->find()->where(['id' => $id])->disableHydration()->first();

        return is_array($row) ? $row : null;
    }

    /**
     * @param string $id
     * @param string $action
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $data
     *
     * @throws \FileStorage\Exception\UploadException
     * @throws \FileStorage\Exception\UploadNotFoundException
     *
     * @return array<string, mixed>
     */
    protected function checked(string $id, string $action, array $context, ?array $data = null): array
    {
        $row = $this->row($id);
        if (!$row) {
            throw new UploadNotFoundException('Upload not found.');
        }
        $this->authorize($action, $row, $context, $data);
        if ($row['expires'] < ($this->clock)()) {
            throw new UploadException('Upload expired.', 410, $row);
        }

        return $row;
    }

    /**
     * @param string $action
     * @param array<string, mixed> $row
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $data
     *
     * @throws \FileStorage\Exception\UploadDeniedException
     *
     * @return string
     */
    protected function authorize(string $action, array $row, array $context, ?array $data = null): string
    {
        $upload = array_intersect_key($row, array_flip(['size', 'model', 'collection', 'filename', 'id']));
        if (isset($row['user_id'])) {
            $upload['owner'] = $row['user_id'];
        }
        if ($data !== null) {
            $upload['data'] = $data;
        }
        $authorizer = Configure::read('FileStorage.resumable.authorizer');
        $result = $authorizer instanceof Closure ? $authorizer($action, $upload, $context) : false;
        $owner = is_array($result) ? ($result['userId'] ?? null) : null;
        if (!is_string($owner) || $owner === '' || strlen($owner) > 36 || (isset($row['user_id']) && $owner !== $row['user_id'])) {
            throw new UploadDeniedException('Upload is not authorized.');
        }

        return $owner;
    }

    /**
     * @param string $id
     * @param string $action
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $data
     *
     * @return array{array<string, mixed>, resource|null}
     */
    protected function locked(string $id, string $action, array $context, ?array $data = null): array
    {
        $row = $this->checked($id, $action, $context, $data);
        if ($row['state'] === 'consumed' && $action === 'write') {
            return [$row, null];
        }
        $handle = null;
        $stat = null;
        try {
            $handle = $this->openPart($this->partPath($id));
            if (is_resource($handle)) {
                if (!flock($handle, LOCK_EX | LOCK_NB)) {
                    throw new UploadLockedException('Upload is locked.', 423, $row);
                }
                $stat = fstat($handle);
                if ($stat === false) {
                    throw new UploadException('Cannot stat part.', 500, $row);
                }
            }
            $row = $this->checked($id, $action, $context, $data);
            if (!is_resource($handle) || ($stat !== null && $stat['nlink'] === 0)) {
                if ($row['state'] !== 'consumed') {
                    throw new UploadException('Part file is missing.', 410, $row);
                }
            }

            return [$row, $handle];
        } catch (Throwable $error) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($error instanceof UploadException) {
                throw new ($error::class)($error->getMessage(), $error->status, $error->upload ?? $row, $error->entity);
            }

            throw new UploadException($error->getMessage(), 500, $row);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $changes
     *
     * @throws \FileStorage\Exception\UploadException
     *
     * @return void
     */
    protected function update(array $row, array $changes): void
    {
        $query = $this->uploads->updateQuery();
        $changes['modified'] = ($this->clock)();
        $changes['revision'] = new QueryExpression('revision + 1');
        $query->set($changes)->where(['id' => $row['id'], 'state' => $row['state'], 'revision' => $row['revision']]);
        $affected = $query->execute()->rowCount();
        if ($affected !== 1) {
            throw new UploadException('Upload changed concurrently.', 409, $row);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param \Cake\I18n\DateTime|null $expiredBefore
     *
     * @throws \FileStorage\Exception\UploadException
     *
     * @return void
     */
    protected function delete(array $row, ?DateTime $expiredBefore = null): void
    {
        $conditions = ['id' => $row['id'], 'state' => $row['state'], 'revision' => $row['revision']];
        if ($expiredBefore !== null) {
            $conditions['expires <'] = $expiredBefore;
        }
        if ($this->uploads->deleteQuery()->where($conditions)->execute()->rowCount() !== 1) {
            throw new UploadException('Upload changed concurrently.', 409, $row);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws \FileStorage\Exception\UploadException
     *
     * @return void
     */
    protected function discard(array $row): void
    {
        $path = $this->partPath($row['id']);
        if ($this->partExists($path) && !$this->unlinkPart($path)) {
            throw new UploadException('Cannot remove part.', 500, $row);
        }
        $this->delete($row);
    }

    /**
     * @param resource $handle
     * @param string $bytes
     *
     * @return int|false
     */
    protected function writePart(mixed $handle, string $bytes): int|false
    {
        return fwrite($handle, $bytes);
    }

    /**
     * @param resource $handle
     * @param int $size
     *
     * @return bool
     */
    protected function truncatePart(mixed $handle, int $size): bool
    {
        return $size >= 0 && ftruncate($handle, $size);
    }

    /**
     * @param resource $handle
     *
     * @return bool
     */
    protected function syncPart(mixed $handle): bool
    {
        return fflush($handle) && fsync($handle);
    }

    protected function unlinkPart(string $path): bool
    {
        return @unlink($path);
    }

    protected function deadline(bool $complete): DateTime
    {
        return ($this->clock)()->addSeconds($this->limit($complete ? 'completedExpires' : 'expires'));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return void
     */
    protected function completed(array $row): void
    {
        // The session is already committed; a listener failure must not turn the completing request into an error.
        try {
            $this->files->dispatchEvent('FileStorage.uploadCompleted', ['upload' => $row]);
        } catch (Throwable $error) {
            Log::warning('Upload completion listener failed: ' . $error->getMessage());
        }
    }

    protected function noTransaction(): void
    {
        if ($this->files->getConnection()->inTransaction()) {
            throw new UploadInTransactionException('Resumable uploads require a connection outside a transaction.');
        }
    }

    protected function directoryPath(): string
    {
        return rtrim((string)Configure::read('FileStorage.resumable.path', TMP . 'file_storage_uploads'), '/\\');
    }

    protected function directory(): void
    {
        $path = $this->directoryPath();
        if (is_link($path)) {
            throw new UploadException('Upload directory cannot be a symlink.');
        }
        if (!is_dir($path)) {
            if (!@mkdir($path, 0700) && !is_dir($path)) {
                throw new UploadException('Cannot create upload directory.');
            }
        }
        $this->syncDirectory(dirname($path));
    }

    protected function syncDirectory(string $path): void
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new UploadException('Cannot open directory for sync.');
        }
        try {
            if (!fsync($handle)) {
                throw new UploadException('Cannot sync directory.');
            }
        } finally {
            fclose($handle);
        }
    }

    protected function partPath(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di', $id)) {
            throw new UploadNotFoundException('Upload not found.');
        }

        return $this->directoryPath() . '/' . $id . '.part';
    }

    protected function partExists(string $path): bool
    {
        clearstatcache(true, $path);
        if (is_link($path)) {
            throw new UploadException('Part cannot be a symlink.');
        }
        if (!file_exists($path)) {
            return false;
        }
        $real = realpath($path);
        $base = realpath($this->directoryPath());
        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new UploadException('Unsafe part path.');
        }

        return true;
    }

    /**
     * @param string $path
     *
     * @throws \FileStorage\Exception\UploadException
     *
     * @return resource|null
     */
    protected function openPart(string $path): mixed
    {
        $this->partExists($path);
        $handle = @fopen($path, 'r+b');
        if ($handle === false) {
            $error = error_get_last();
            if (!$this->partExists($path) && str_contains($error['message'] ?? '', 'No such file or directory')) {
                return null;
            }

            throw new UploadException('Cannot open part.');
        }

        try {
            $opened = fstat($handle);
            if ($opened === false) {
                throw new UploadException('Cannot stat opened part.');
            }
            if ($this->partExists($path)) {
                $current = @stat($path);
                if ($current !== false && ($current['dev'] !== $opened['dev'] || $current['ino'] !== $opened['ino'])) {
                    throw new UploadException('Part path changed while opening.');
                }
            }
        } catch (Throwable $error) {
            fclose($handle);

            throw $error;
        }

        return $handle;
    }

    /**
     * @throws \FileStorage\Exception\UploadException
     *
     * @return resource
     */
    protected function openAdmission(): mixed
    {
        $path = $this->directoryPath() . '/.admission';
        if (is_link($path)) {
            throw new UploadException('Unsafe admission path.');
        }
        $handle = @fopen($path, 'c+b');
        if ($handle === false || !chmod($path, 0600)) {
            throw new UploadException('Cannot open admission lock.');
        }

        return $handle;
    }

    /**
     * @param \Psr\Http\Message\StreamInterface|resource $body
     *
     * @throws \FileStorage\Exception\UploadException
     * @throws \FileStorage\Exception\UploadInvalidException
     *
     * @return string
     */
    protected function read(mixed $body): string
    {
        if ($body instanceof StreamInterface) {
            $block = $body->read(65536);
            if ($block === '' && !$body->eof()) {
                throw new UploadException('Body stream made no progress.');
            }

            return $block;
        }
        if (!is_resource($body)) {
            throw new UploadInvalidException('Body must be a stream.');
        }
        $block = fread($body, 65536);
        if ($block === false || ($block === '' && !feof($body))) {
            throw new UploadException('Cannot read body.');
        }

        return $block;
    }
}
