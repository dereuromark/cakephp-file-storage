<?php declare(strict_types=1);

// phpcs:disable PhpCollective.Testing.AssertPrimitives

namespace FileStorage\Test\TestCase\Service;

use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\TestSuite\Fixture\FixtureHelper;
use Cake\TestSuite\TestCase;
use FileStorage\Service\BlobRegistry;
use FileStorage\Test\Fixture\FileStorageFixture;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Tests blob locks across independent connections.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobRegistryConcurrencyTest extends TestCase
{
    protected ?Connection $connectionA = null;

    protected ?Connection $connectionB = null;

    protected BlobRegistry $registryA;

    protected BlobRegistry $registryB;

    public function setUp(): void
    {
        parent::setUp();
        $connection = ConnectionManager::get('test');
        if ($connection->getDriver() instanceof Sqlite) {
            $this->markTestSkipped('Concurrency tests require MySQL or PostgreSQL.');
        }
        $this->connectionA = $connection;
        ConnectionManager::setConfig('blob_registry_second', ConnectionManager::getConfig('test'));
        $this->connectionB = ConnectionManager::get('blob_registry_second');
        $this->registryA = new BlobRegistry(new Table(['table' => 'file_storage', 'connection' => $this->connectionA]));
        $this->registryB = new BlobRegistry(new Table(['table' => 'file_storage', 'connection' => $this->connectionB]));
        $this->connectionA->execute('DELETE FROM file_storage');
        $this->connectionA->execute('DELETE FROM file_storage_blobs');
    }

    public function tearDown(): void
    {
        foreach ([$this->connectionB, $this->connectionA] as $connection) {
            if ($connection !== null && $connection->inTransaction()) {
                $connection->rollback();
            }
        }
        if ($this->connectionA !== null) {
            $this->connectionA->execute('DELETE FROM file_storage');
            $this->connectionA->execute('DELETE FROM file_storage_blobs');
            (new FixtureHelper())->truncate([new FileStorageFixture()]);
        }
        if ($this->connectionB !== null) {
            $this->connectionB->getDriver()->disconnect();
            ConnectionManager::drop('blob_registry_second');
        }
        parent::tearDown();
    }

    protected function oldBlob(): int
    {
        $this->connectionA->begin();
        $claim = $this->registryA->claim('Local', 'hash', new DateTime('2020-01-01'));
        $this->registryA->recordPath($claim->id, 'blob');
        $this->connectionA->commit();

        return $claim->id;
    }

    protected function reference(int $id): void
    {
        $this->connectionA->execute(
            'INSERT INTO file_storage (uuid, filename, blob_id) VALUES (:uuid, :filename, :id)',
            ['uuid' => '10000000-0000-4000-8000-000000000099', 'filename' => 'file', 'id' => $id],
            ['id' => 'integer'],
        );
    }

    public function testTwoFirstUploads(): void
    {
        $this->connectionA->begin();
        $first = $this->registryA->claim('Local', 'hash', new DateTime('2020-01-01'));
        $this->assertSame(null, $first->path);
        $this->connectionB->begin();
        try {
            $this->registryB->withLockWaitLimit(fn () => $this->registryB->claim('Local', 'hash', new DateTime('2020-01-02')));
            $this->fail('Second claim did not time out');
        } catch (PDOException $exception) {
            $this->assertSame(true, $this->registryB->isLockTimeout($exception));
        } finally {
            $this->connectionB->rollback();
        }
        $this->registryA->recordPath($first->id, 'blob');
        $this->connectionA->commit();
        $this->connectionB->begin();
        $second = $this->registryB->withLockWaitLimit(fn () => $this->registryB->claim('Local', 'hash', new DateTime('2020-01-02')));
        $this->connectionB->commit();
        $this->assertSame($first->id, $second->id);
        $this->assertSame('blob', $second->path);
    }

    public function testSweepDuringUpload(): void
    {
        $id = $this->oldBlob();
        $this->connectionA->begin();
        $this->registryA->claim('Local', 'hash', new DateTime('2022-01-01'));
        $deleteFile = function (): void {
            $this->fail('Sweep deleted an uploading blob');
        };
        $result = $this->registryB->sweep(new DateTime('2021-01-01'), $deleteFile);
        $this->assertSame(['deleted' => [], 'skipped' => 1, 'warnings' => []], $result);
        $this->assertSame(false, $this->connectionB->inTransaction());
        $this->reference($id);
        $this->connectionA->commit();
        $this->assertSame(['deleted' => [], 'skipped' => 0, 'warnings' => []], $this->registryB->sweep(new DateTime('2021-01-01'), $deleteFile));
    }

    public function testUploadLongerThanGracePeriod(): void
    {
        $id = $this->oldBlob();
        $this->connectionA->begin();
        $this->registryA->claim('Local', 'hash', new DateTime('2020-02-01'));
        $this->reference($id);
        $this->connectionA->commit();
        $result = $this->registryB->sweep(new DateTime('2021-01-01'), function (): void {
            $this->fail('Sweep deleted a referenced blob');
        });
        $this->assertSame(['deleted' => [], 'skipped' => 1, 'warnings' => []], $result);
    }

    public function testForeignKeyBackstop(): void
    {
        $id = $this->oldBlob();
        $this->reference($id);
        $this->connectionB->begin();
        try {
            $this->registryB->withLockWaitLimit(fn () => $this->connectionB->execute('DELETE FROM file_storage_blobs WHERE id = :id', ['id' => $id], ['id' => 'integer']));
            $this->fail('Foreign key allowed deletion');
        } catch (PDOException $exception) {
            $this->assertSame(true, $this->registryB->isForeignKeyViolation($exception));
        } finally {
            $this->connectionB->rollback();
        }
    }

    public function testStrayDeletionAgainstClaim(): void
    {
        $this->connectionA->begin();
        $claim = $this->registryA->claim('Local', 'hash', new DateTime('2020-01-01'));
        $deleteFile = function (): void {
            $this->fail('Stray removal deleted an uploading blob');
        };
        $this->assertSame(false, $this->registryB->removeStray('Local', 'hash', 'blob', $deleteFile));
        $this->assertSame(false, $this->connectionB->inTransaction());
        $this->registryA->recordPath($claim->id, 'blob');
        $this->connectionA->commit();
        $this->assertSame(false, $this->registryB->removeStray('Local', 'hash', 'blob', $deleteFile));
    }

    #[DataProvider('cleanupMethods')]
    public function testCleanupRequiresFreshTransaction(string $method): void
    {
        $this->connectionB->begin();
        $this->connectionB->execute('SELECT 1');
        $this->expectException(RuntimeException::class);
        if ($method === 'sweep') {
            $this->registryB->sweep(new DateTime('2021-01-01'), static function (): void {
            });

            return;
        }
        $this->registryB->removeStray('Local', 'hash', 'blob', static function (): void {
        });
    }

    public static function cleanupMethods(): array
    {
        return [['sweep'], ['removeStray']];
    }
}
