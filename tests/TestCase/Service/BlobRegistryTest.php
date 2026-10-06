<?php declare(strict_types=1);

// phpcs:disable PhpCollective.Testing.AssertPrimitives

namespace FileStorage\Test\TestCase\Service;

use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use FileStorage\Model\Table\FileStorageBlobsTable;
use FileStorage\Service\BlobClaim;
use FileStorage\Service\BlobRegistry;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Tests the blob claim and cleanup protocol.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobRegistryTest extends TestCase
{
    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    protected Connection $connection;

    protected BlobRegistry $registry;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
        $this->registry = new BlobRegistry(new Table(['table' => 'file_storage', 'connection' => $this->connection]), 1, static fn () => new DateTime('2020-01-01'));
        $this->connection->execute('DELETE FROM file_storage');
        $this->connection->execute('DELETE FROM file_storage_blobs');
    }

    public function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollback();
        }
        $this->connection->execute('DELETE FROM file_storage');
        $this->connection->execute('DELETE FROM file_storage_blobs');
        parent::tearDown();
    }

    protected function blob(string $hash, ?string $path = 'blob', string $date = '2020-01-01', string $adapter = 'Local'): BlobClaim
    {
        $this->connection->begin();
        $claim = $this->registry->claim($adapter, $hash, new DateTime($date));
        if ($path !== null) {
            $this->registry->recordPath($claim->id, $path);
        }
        $this->connection->commit();

        return $claim;
    }

    protected function reference(int $id): void
    {
        $this->connection->execute(
            'INSERT INTO file_storage (uuid, filename, blob_id) VALUES (:uuid, :filename, :id)',
            ['uuid' => '10000000-0000-4000-8000-000000000099', 'filename' => 'file', 'id' => $id],
            ['id' => 'integer'],
        );
    }

    protected function countBlobs(): int
    {
        return (int)$this->connection->execute('SELECT COUNT(*) FROM file_storage_blobs')->fetchColumn(0);
    }

    public function testClaimRequiresTransaction(): void
    {
        $this->expectException(RuntimeException::class);
        $this->registry->claim('Local', 'hash', new DateTime('2020-01-01'));
    }

    public function testRecordPathRequiresTransaction(): void
    {
        $this->expectException(RuntimeException::class);
        $this->registry->recordPath(1, 'blob');
    }

    public function testClaimUpdatesTouchedAndReturnsPath(): void
    {
        $first = $this->blob('hash');
        $this->assertSame(null, $first->path);
        $this->connection->begin();
        $second = $this->registry->claim('Local', 'hash', new DateTime('2021-01-01'));
        $this->connection->commit();
        $this->assertSame($first->id, $second->id);
        $this->assertSame('blob', $second->path);
        $this->assertSame('2021-01-01 00:00:00', $this->connection->execute('SELECT touched FROM file_storage_blobs')->fetchColumn(0));
    }

    public function testAdaptersHaveSeparateClaims(): void
    {
        $first = $this->blob('hash');
        $second = $this->blob('hash', 'other', '2020-01-01', 'S3');
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $this->countBlobs());
    }

    public function testSweep(): void
    {
        $this->blob('old', 'old');
        $this->blob('young', 'young', '2022-01-01');
        $referenced = $this->blob('referenced', 'referenced');
        $this->reference($referenced->id);
        $this->blob('null', null);
        $calls = [];
        $result = $this->registry->sweep(new DateTime('2021-01-01'), static function ($adapter, $path) use (&$calls): void {
            $calls[] = [$adapter, $path];
        });
        $this->assertSame([['Local', 'old']], $calls);
        $this->assertSame(['deleted' => ['old'], 'skipped' => 1, 'warnings' => []], $result);
        $this->assertSame(2, $this->countBlobs());
    }

    public function testSweepFailureContinues(): void
    {
        $this->blob('bad', 'bad');
        $this->blob('good', 'good');
        $result = $this->registry->sweep(new DateTime('2021-01-01'), static function ($adapter, $path): void {
            if ($path === 'bad') {
                throw new RuntimeException('Storage failed');
            }
        });
        $this->assertSame(['Storage failed'], $result['warnings']);
        $this->assertSame(['good'], $result['deleted']);
        $this->assertSame(1, $this->countBlobs());
    }

    public function testSweepRollsBackForeignKeyViolationAndContinues(): void
    {
        $this->blob('raced', 'raced');
        $this->blob('next', 'next');
        $registry = new class (new Table(['table' => 'file_storage', 'connection' => $this->connection])) extends BlobRegistry {
            protected bool $injectReference = true;

            protected function deleteRow(int $id): void
            {
                if ($this->injectReference) {
                    $this->injectReference = false;
                    $this->connection->execute(
                        'INSERT INTO file_storage (uuid, filename, blob_id) VALUES (:uuid, :filename, :id)',
                        ['uuid' => '10000000-0000-4000-8000-000000000098', 'filename' => 'file', 'id' => $id],
                        ['id' => 'integer'],
                    );
                }
                parent::deleteRow($id);
            }
        };
        $calls = [];
        $result = $registry->sweep(new DateTime('2021-01-01'), static function ($adapter, $path) use (&$calls): void {
            $calls[] = $path;
        });
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, count($result['deleted']));
        $this->assertSame($result['deleted'], $calls);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(1, $this->countBlobs());
        $this->assertSame(0, (int)$this->connection->execute('SELECT COUNT(*) FROM file_storage')->fetchColumn(0));
        $this->assertSame(false, $this->connection->inTransaction());
    }

    public function testDryRun(): void
    {
        $this->blob('hash');
        $result = $this->registry->sweep(new DateTime('2021-01-01'), function (): void {
            $this->fail('Dry run called storage');
        }, true);
        $this->assertSame(['deleted' => ['blob'], 'skipped' => 0, 'warnings' => []], $result);
        $this->assertSame(1, $this->countBlobs());
    }

    public function testRemoveStray(): void
    {
        $calls = [];
        $this->assertSame(true, $this->registry->removeStray('Local', 'hash', 'stray', static function ($adapter, $path) use (&$calls): void {
            $calls[] = [$adapter, $path];
        }));
        $this->assertSame([['Local', 'stray']], $calls);
        $this->assertSame(0, $this->countBlobs());
    }

    public function testRemoveStrayPreservesStoredRow(): void
    {
        $this->blob('hash');
        $before = $this->connection->execute('SELECT * FROM file_storage_blobs')->fetchAll('assoc');
        $this->assertSame(false, $this->registry->removeStray('Local', 'hash', 'stray', function (): void {
            $this->fail('Stored blob was deleted');
        }));
        $this->assertSame($before, $this->connection->execute('SELECT * FROM file_storage_blobs')->fetchAll('assoc'));
    }

    public function testRemoveStrayPreservesReferencedNullPath(): void
    {
        $claim = $this->blob('hash', null);
        $this->reference($claim->id);
        $this->assertSame(false, $this->registry->removeStray('Local', 'hash', 'stray', function (): void {
            $this->fail('Referenced blob was deleted');
        }));
        $this->assertSame(1, $this->countBlobs());
    }

    public function testRemoveStrayFailureRollsBack(): void
    {
        try {
            $this->registry->removeStray('Local', 'hash', 'stray', static function (): void {
                throw new RuntimeException('Storage failed');
            });
            $this->fail('Expected storage exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('Storage failed', $exception->getMessage());
        }
        $this->assertSame(0, $this->countBlobs());
        $this->assertSame(false, $this->connection->inTransaction());
    }

    #[DataProvider('cleanupMethods')]
    public function testCleanupRejectsExistingTransaction(string $method): void
    {
        $this->connection->begin();
        $this->expectException(RuntimeException::class);
        if ($method === 'sweep') {
            $this->registry->sweep(new DateTime('2021-01-01'), static function (): void {
            });

            return;
        }
        $this->registry->removeStray('Local', 'hash', 'stray', static function (): void {
        });
    }

    public static function cleanupMethods(): array
    {
        return [['sweep'], ['removeStray']];
    }

    public function testUnsupportedDriver(): void
    {
        $driver = $this->createStub(Driver::class);
        $driver->method('enabled')->willReturn(true);
        $connection = new Connection(['driver' => $driver]);
        $this->expectException(RuntimeException::class);
        new BlobRegistry(new Table(['connection' => $connection]));
    }

    public function testForeignKeyRejectsDirectDeletion(): void
    {
        $claim = $this->blob('hash');
        $this->reference($claim->id);
        $this->connection->begin();
        try {
            $this->connection->execute('DELETE FROM file_storage_blobs WHERE id = :id', ['id' => $claim->id], ['id' => 'integer']);
            $this->fail('Foreign key allowed deletion');
        } catch (PDOException $exception) {
            $this->assertSame(true, $this->registry->isForeignKeyViolation($exception));
        } finally {
            $this->connection->rollback();
        }
        $this->assertSame(1, $this->countBlobs());
    }

    public function testBlobsTableHasNoBehaviors(): void
    {
        $table = new FileStorageBlobsTable(['connection' => $this->connection]);
        $this->assertSame('file_storage_blobs', $table->getTable());
        $this->assertSame([], $table->behaviors()->loaded());
    }
}
