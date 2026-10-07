<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\TableLocator;
use FileStorage\Model\Table\FileStorageTable;
use FileStorage\Service\BlobRegistry;
use FileStorage\Service\ExistingFileDeduplicator;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\Config;

/**
 * Checks conversion against an upload holding the same blob lock.
 *
 * @author Mark Scherer
 * @license MIT
 */
class ExistingFileDeduplicatorConcurrencyTest extends FileStorageTestCase
{
    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    protected ?Connection $secondConnection = null;

    public function setUp(): void
    {
        parent::setUp();
        if ($this->FileStorage->getConnection()->getDriver() instanceof Sqlite) {
            $this->markTestSkipped('Concurrency tests require MySQL or PostgreSQL.');
        }
        ConnectionManager::setConfig('deduplicator_second', ['persistent' => false] + (array)ConnectionManager::getConfig('test'));
        $this->secondConnection = ConnectionManager::get('deduplicator_second');
        $this->FileStorage = $this->fetchTable('FileStorage.FileStorage');
        $this->FileStorage->deleteAll([]);
    }

    public function tearDown(): void
    {
        if ($this->secondConnection !== null) {
            if ($this->secondConnection->inTransaction()) {
                $this->secondConnection->rollback();
            }
            $this->secondConnection->getDriver()->disconnect();
            ConnectionManager::drop('deduplicator_second');
        }
        $connection = $this->FileStorage->getConnection();
        if ($connection->inTransaction()) {
            $connection->rollback();
        }
        Configure::delete('FileStorage.deduplicate');
        parent::tearDown();
    }

    public function testUploadLockTimeoutThenReuse(): void
    {
        Configure::write('FileStorage.deduplicate.collections', false);
        $this->FileStorage->removeBehavior('FileStorage');
        $this->FileStorage->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
        $row = $this->FileStorage->newEntity([
            'model' => 'Items',
            'collection' => 'Photos',
            'file' => new UploadedFile($this->fileFixtures . 'titus.jpg', filesize($this->fileFixtures . 'titus.jpg'), UPLOAD_ERR_OK, 'titus.jpg', 'image/jpeg'),
        ]);
        $this->FileStorage->saveOrFail($row);
        Configure::write('FileStorage.deduplicate.collections', true);
        $bytes = file_get_contents($this->testPath . $row->path);
        $connection = $this->FileStorage->getConnection();
        $registry = new BlobRegistry($this->FileStorage);
        $connection->begin();
        $claim = $registry->claim('Local', $row->hash, DateTime::now());
        $path = 'blobs/' . $row->hash . '.jpg';
        Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local')->write($path, $bytes, new Config());
        $registry->recordPath($claim->id, $path);
        $locator = new TableLocator();
        $locator->set('FileStorage.FileStorage', new FileStorageTable(['connection' => $this->secondConnection]));
        $service = new ExistingFileDeduplicator();
        $service->setTableLocator($locator);
        $report = $service->run();
        $this->assertSame(0, $report->converted);
        $this->assertSame(1, $report->failures);
        $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
        $this->assertNull($this->FileStorage->get($row->id)->blob_id);
        $this->assertFalse($this->secondConnection->inTransaction());
        $connection->commit();
        $report = $service->run();
        $this->assertSame(1, $report->converted);
        $this->assertSame(1, $report->reusedExisting);
        $this->assertSame($path, $this->FileStorage->get($row->id)->path);
        $this->assertSame($claim->id, $this->FileStorage->get($row->id)->blob_id);
        $this->assertSame($bytes, file_get_contents($this->testPath . $row->path));
    }
}
