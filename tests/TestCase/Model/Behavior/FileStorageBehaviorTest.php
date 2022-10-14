<?php

declare(strict_types = 1);

namespace FileStorage\Test\TestCase\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;
use FileStorage\Test\TestCase\FileStorageTestCase;
use FileStorage\Test\TestCase\FileStorageTestTable;
use Laminas\Diactoros\UploadedFile;

/**
 * StorageBehaviorTest
 */
class FileStorageBehaviorTest extends FileStorageTestCase
{
    /**
     * Holds the instance of the table
     *
     * @var \FileStorage\Model\Table\FileStorageTable
     */
    protected $FileStorage;

    /**
     * startTest
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->getTableLocator()->clear();
        $this->FileStorage = $this->getTableLocator()->get(FileStorageTestTable::class);

        $this->FileStorage->addBehavior(
            'FileStorage.FileStorage',
            Configure::read('FileStorage.behaviorConfig'),
        );

        $this->testFilePath = Plugin::path('FileStorage') . 'Test' . DS . 'Fixture' . DS . 'File' . DS;
    }

    /**
     * endTest
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->FileStorage);
        $this->getTableLocator()->clear();
    }

    /**
     * testAfterDelete
     *
     * @return void
     */
    public function testAfterDelete()
    {
        $file = $this->_createMockFile('/Item/00/14/90/filestorage1/filestorage1.png');
        $this->assertFileExists($file);

        $entity = $this->FileStorage->get(1);
        $entity->adapter = 'Local';
        $entity->path = '/Item/00/14/90/filestorage1/filestorage1.png';

        $event = new Event('FileStorage.afterDelete', $this->FileStorage, [
            'entity' => $entity,
            'adapter' => 'Local',
        ]);

        $this->FileStorage->behaviors()->FileStorage->afterDelete(
            $event,
            $entity,
            new ArrayObject([]),
        );

        $this->assertFileDoesNotExist($file);
    }

    /**
     * testBeforeSave
     *
     * @return void
     */
    public function testBeforeSave()
    {
        $file = new UploadedFile(
            $this->fileFixtures . 'titus.jpg',
            filesize($this->fileFixtures . 'titus.jpg'),
            UPLOAD_ERR_OK,
            'titus.png',
            'image/jpeg',
        );

        $entity = $this->FileStorage->newEntity([
            'file' => $file,
        ], [
            'accessibleFields' => ['*' => true],
        ]);

        $event = new Event('Model.beforeSave', $this->FileStorage, [
            'entity' => $entity,
        ]);

        $this->FileStorage->behaviors()->FileStorage->beforeSave($event, $entity, new ArrayObject([]));

        $this->assertSame($entity->adapter, 'Local');
        $this->assertSame($entity->filesize, 332643);
        $this->assertSame($entity->mime_type, 'image/jpeg');
        $this->assertSame($entity->model, 'FileStorage');
    }

    /**
     * @return void
     */
    public function testBeforeSaveArray()
    {
        $entity = $this->FileStorage->newEntity([
            'file' => [
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $this->fileFixtures . 'titus.jpg',
                'size' => filesize($this->fileFixtures . 'titus.jpg'),
                'name' => 'titus.png',
                'type' => 'image/jpeg',
            ],
        ], [
            'accessibleFields' => ['*' => true],
        ]);

        $event = new Event('Model.beforeSave', $this->FileStorage, [
            'entity' => $entity,
        ]);

        $this->FileStorage->behaviors()->FileStorage->beforeSave($event, $entity, new ArrayObject([]));

        $this->assertSame($entity->adapter, 'Local');
        $this->assertSame($entity->filesize, 332643);
        $this->assertSame($entity->mime_type, 'image/jpeg');
        $this->assertSame($entity->model, 'FileStorage');
    }
}
