<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Closure;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Claims and removes blobs under database locks.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobRegistry
{
    protected Connection $connection;

    protected string $filesTable;

    protected string $lockClause;

    protected Closure $clock;

    public function __construct(Table $files, protected int $lockWaitSeconds = 1, ?Closure $clock = null)
    {
        $this->connection = $files->getConnection();
        $driver = $this->connection->getDriver();
        if (!$driver instanceof Mysql && !$driver instanceof Postgres && !$driver instanceof Sqlite) {
            throw new RuntimeException('Unsupported blob registry database driver.');
        }
        if ($lockWaitSeconds < 1) {
            throw new RuntimeException('The lock wait limit must be at least one second.');
        }
        if ($driver instanceof Sqlite) {
            $version = $this->connection->execute('SELECT sqlite_version()')->fetchColumn(0);
            if (version_compare((string)$version, '3.24.0', '<')) {
                throw new RuntimeException('BlobRegistry requires SQLite 3.24.0 or newer.');
            }
        }
        $this->filesTable = $driver->quoteIdentifier($files->getTable());
        $this->lockClause = $driver instanceof Sqlite ? '' : ' FOR UPDATE';
        $this->clock = $clock ?? static fn (): DateTime => DateTime::now();
    }

    public function claim(string $adapter, string $hash, DateTime $now): BlobClaim
    {
        $this->requireTransaction();
        $sql = 'INSERT INTO file_storage_blobs (adapter, hash, touched, created) VALUES (:adapter, :hash, :touched, :created)';
        $params = ['adapter' => $adapter, 'hash' => $hash, 'touched' => $now, 'created' => $now];
        $types = ['touched' => 'datetime', 'created' => 'datetime'];
        if ($this->connection->getDriver() instanceof Mysql) {
            $sql .= ' ON DUPLICATE KEY UPDATE touched = :updated';
            $params['updated'] = $now;
            $types['updated'] = 'datetime';
        } else {
            $sql .= ' ON CONFLICT (adapter, hash) DO UPDATE SET touched = excluded.touched';
        }
        $this->connection->execute($sql, $params, $types);
        $row = $this->connection->execute(
            'SELECT id, path FROM file_storage_blobs WHERE adapter = :adapter AND hash = :hash' . $this->lockClause,
            ['adapter' => $adapter, 'hash' => $hash],
        )->fetch('assoc');
        if (!$row) {
            throw new RuntimeException('Claimed blob could not be read.');
        }

        return new BlobClaim((int)$row['id'], $row['path']);
    }

    public function recordPath(int $blobId, string $path): void
    {
        $this->requireTransaction();
        $this->connection->execute(
            'UPDATE file_storage_blobs SET path = :path WHERE id = :id',
            ['path' => $path, 'id' => $blobId],
            ['id' => 'integer'],
        );
    }

    /**
     * @param \Cake\I18n\DateTime $olderThan
     * @param \Closure(string, string): void $deleteFile
     * @param bool $dryRun
     *
     * @return array{deleted: array<int, string>, skipped: int, warnings: array<int, string>}
     */
    public function sweep(DateTime $olderThan, Closure $deleteFile, bool $dryRun = false): array
    {
        $this->requireNoTransaction();
        $candidates = $this->connection->execute(
            'SELECT id FROM file_storage_blobs WHERE touched < :older',
            ['older' => $olderThan],
            ['older' => 'datetime'],
        )->fetchAll('assoc');
        $result = ['deleted' => [], 'skipped' => 0, 'warnings' => []];
        foreach ($candidates as $candidate) {
            $this->connection->begin();
            try {
                $row = $this->withLockWaitLimit(fn () => $this->connection->execute(
                    'SELECT id, adapter, path FROM file_storage_blobs WHERE id = :id AND touched < :older' . $this->lockClause,
                    ['id' => $candidate['id'], 'older' => $olderThan],
                    ['id' => 'integer', 'older' => 'datetime'],
                )->fetch('assoc'));
                if (!$row || $this->hasReference((int)$row['id'])) {
                    $this->connection->rollback();
                    $result['skipped']++;

                    continue;
                }
                if ($dryRun) {
                    $this->connection->rollback();
                    if ($row['path'] !== null) {
                        $result['deleted'][] = $row['path'];
                    }

                    continue;
                }
                try {
                    $this->deleteRow((int)$row['id']);
                } catch (Throwable $exception) {
                    if (!$this->isForeignKeyViolation($exception)) {
                        throw $exception;
                    }
                    $this->connection->rollback();
                    $result['skipped']++;

                    continue;
                }
                if ($row['path'] !== null) {
                    try {
                        $deleteFile($row['adapter'], $row['path']);
                    } catch (Throwable $exception) {
                        $this->connection->rollback();
                        $result['warnings'][] = $exception->getMessage();

                        continue;
                    }
                }
                $this->connection->commit();
                if ($row['path'] !== null) {
                    $result['deleted'][] = $row['path'];
                }
            } catch (Throwable $exception) {
                $this->connection->rollback();
                if (!$this->isLockTimeout($exception)) {
                    throw $exception;
                }
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * @param string $adapter
     * @param string $hash
     * @param string $path
     * @param \Closure(string, string): void $deleteFile
     *
     * @return bool
     */
    public function removeStray(string $adapter, string $hash, string $path, Closure $deleteFile): bool
    {
        $this->requireNoTransaction();
        $this->connection->begin();
        try {
            $row = $this->withLockWaitLimit(function () use ($adapter, $hash) {
                $sql = 'INSERT INTO file_storage_blobs (adapter, hash, path, touched, created) VALUES (:adapter, :hash, NULL, :touched, :created)';
                $sql .= $this->connection->getDriver() instanceof Mysql
                    ? ' ON DUPLICATE KEY UPDATE id = id'
                    : ' ON CONFLICT (adapter, hash) DO NOTHING';
                $now = ($this->clock)();
                $this->connection->execute($sql, ['adapter' => $adapter, 'hash' => $hash, 'touched' => $now, 'created' => $now], ['touched' => 'datetime', 'created' => 'datetime']);

                return $this->connection->execute(
                    'SELECT id, path FROM file_storage_blobs WHERE adapter = :adapter AND hash = :hash' . $this->lockClause,
                    ['adapter' => $adapter, 'hash' => $hash],
                )->fetch('assoc');
            });
            if (!$row || $row['path'] !== null || $this->hasReference((int)$row['id'])) {
                $this->connection->rollback();

                return false;
            }
        } catch (Throwable $exception) {
            $this->connection->rollback();
            if ($this->isLockTimeout($exception)) {
                return false;
            }

            throw $exception;
        }
        try {
            $deleteFile($adapter, $path);
            $this->deleteRow((int)$row['id']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollback();

            throw $exception;
        }

        return true;
    }

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function withLockWaitLimit(Closure $operation): mixed
    {
        $this->requireTransaction();
        $driver = $this->connection->getDriver();
        if ($driver instanceof Postgres) {
            $this->connection->execute("SELECT set_config('lock_timeout', :timeout, true)", ['timeout' => $this->lockWaitSeconds . 's']);

            return $operation();
        }
        if (!$driver instanceof Mysql) {
            return $operation();
        }
        $previous = (int)$this->connection->execute('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn(0);
        $this->connection->execute('SET SESSION innodb_lock_wait_timeout = :timeout', ['timeout' => $this->lockWaitSeconds], ['timeout' => 'integer']);
        try {
            return $operation();
        } finally {
            $this->connection->execute('SET SESSION innodb_lock_wait_timeout = :timeout', ['timeout' => $previous], ['timeout' => 'integer']);
        }
    }

    public function isLockTimeout(Throwable $exception): bool
    {
        for ($error = $exception; $error !== null; $error = $error->getPrevious()) {
            if (!$error instanceof PDOException) {
                continue;
            }
            $info = $error->errorInfo ?? [];
            if ($this->connection->getDriver() instanceof Mysql && (int)($info[1] ?? 0) === 1205) {
                return true;
            }
            if ($this->connection->getDriver() instanceof Postgres && ($info[0] ?? '') === '55P03') {
                return true;
            }
        }

        return false;
    }

    public function isForeignKeyViolation(Throwable $exception): bool
    {
        for ($error = $exception; $error !== null; $error = $error->getPrevious()) {
            if (!$error instanceof PDOException) {
                continue;
            }
            $info = $error->errorInfo ?? [];
            $driver = $this->connection->getDriver();
            if ($driver instanceof Mysql && in_array((int)($info[1] ?? 0), [1451, 1452], true)) {
                return true;
            }
            if ($driver instanceof Postgres && ($info[0] ?? '') === '23503') {
                return true;
            }
            if ($driver instanceof Sqlite && (int)($info[1] ?? 0) === 19 && str_contains($error->getMessage(), 'FOREIGN KEY constraint failed')) {
                return true;
            }
        }

        return false;
    }

    protected function hasReference(int $id): bool
    {
        return $this->connection->execute(
            'SELECT id FROM ' . $this->filesTable . ' WHERE blob_id = :id LIMIT 1',
            ['id' => $id],
            ['id' => 'integer'],
        )->fetch('assoc') !== false;
    }

    protected function deleteRow(int $id): void
    {
        $this->connection->execute('DELETE FROM file_storage_blobs WHERE id = :id', ['id' => $id], ['id' => 'integer']);
    }

    protected function requireTransaction(): void
    {
        if (!$this->connection->inTransaction()) {
            throw new RuntimeException('Blob claims and path updates require a transaction.');
        }
    }

    protected function requireNoTransaction(): void
    {
        if ($this->connection->inTransaction()) {
            throw new RuntimeException('Blob cleanup requires a connection outside a transaction.');
        }
    }
}
