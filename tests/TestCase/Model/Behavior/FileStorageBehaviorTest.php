<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Event\Event;
use FileStorage\Model\Table\FileStorageTable;
use FileStorage\Test\TestCase\FileStorageTestCase;
use FilesystemIterator;
use Laminas\Diactoros\UploadedFile;
use PhpCollective\Infrastructure\Storage\FileInterface;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

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
     * @var string
     */
    protected $testFilePath;

    /**
     * startTest
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->getTableLocator()->clear();
        $this->FileStorage = $this->getTableLocator()->get(FileStorageTable::class);

        $this->FileStorage->addBehavior(
            'FileStorage.FileStorage',
            Configure::read('FileStorage.behaviorConfig'),
        );

        //$this->testFilePath = Plugin::path('FileStorage') . 'Test' . DS . 'Fixture' . DS . 'File' . DS;
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
    }

    /**
     * Test processImages() with matching model and collection configuration
     *
     * @return void
     */
    public function testProcessImagesWithMatchingConfig()
    {
        Configure::write('FileStorage.imageVariants', [
            'FileStorage' => [
                'Photos' => [
                    'thumbnail' => [
                        'width' => 50,
                        'height' => 50,
                    ],
                ],
            ],
        ]);

        $entity = $this->FileStorage->newEmptyEntity();
        $entity->collection = 'Photos';
        $entity->id = 'test-id';
        $entity->path = 'test/path.jpg';
        $entity->adapter = 'Local';

        $file = $this->FileStorage->behaviors()->FileStorage->entityToFileObject($entity);

        $result = $this->FileStorage->behaviors()->FileStorage->processImages($file, $entity);

        $this->assertNotEmpty($result->variants());
        $this->assertArrayHasKey('thumbnail', $result->variants());
    }

    /**
     * Test processImages() with model != collection (the bug fix scenario)
     *
     * @return void
     */
    public function testProcessImagesWithDifferentModelAndCollection()
    {
        Configure::write('FileStorage.imageVariants', [
            'FileStorage' => [
                'Cover' => [
                    'large' => [
                        'width' => 800,
                        'height' => 600,
                    ],
                ],
            ],
        ]);

        $entity = $this->FileStorage->newEmptyEntity();
        $entity->collection = 'Cover';
        $entity->id = 'test-id';
        $entity->path = 'test/path.jpg';
        $entity->adapter = 'Local';

        $file = $this->FileStorage->behaviors()->FileStorage->entityToFileObject($entity);

        $result = $this->FileStorage->behaviors()->FileStorage->processImages($file, $entity);

        $this->assertNotEmpty($result->variants());
        $this->assertArrayHasKey('large', $result->variants());
    }

    /**
     * @return void
     */
    public function testProcessImagesUsesTableAliasByDefault(): void
    {
        Configure::write('FileStorage.imageVariants', [
            'FileStorage' => [
                'Logo' => [
                    'legacy' => [
                        'width' => 800,
                        'height' => 600,
                    ],
                ],
            ],
            'Issuers' => [
                'Logo' => [
                    'entityModel' => [
                        'width' => 400,
                        'height' => 300,
                    ],
                ],
            ],
        ]);

        $entity = $this->FileStorage->newEmptyEntity();
        $entity->model = 'Issuers';
        $entity->collection = 'Logo';
        $entity->id = 'test-id';
        $entity->path = 'test/path.jpg';
        $entity->adapter = 'Local';

        $file = $this->FileStorage->behaviors()->FileStorage->entityToFileObject($entity);

        $result = $this->FileStorage->behaviors()->FileStorage->processImages($file, $entity);

        $this->assertArrayHasKey('legacy', $result->variants());
        $this->assertArrayNotHasKey('entityModel', $result->variants());
    }

    /**
     * @return void
     */
    public function testProcessImagesUsesEntityModelWhenEnabled(): void
    {
        Configure::write('FileStorage.useEntityModelForVariants', true);
        Configure::write('FileStorage.imageVariants', [
            'FileStorage' => [
                'Logo' => [
                    'legacy' => [
                        'width' => 800,
                        'height' => 600,
                    ],
                ],
            ],
            'Issuers' => [
                'Logo' => [
                    'entityModel' => [
                        'width' => 400,
                        'height' => 300,
                    ],
                ],
            ],
        ]);

        $entity = $this->FileStorage->newEmptyEntity();
        $entity->model = 'Issuers';
        $entity->collection = 'Logo';
        $entity->id = 'test-id';
        $entity->path = 'test/path.jpg';
        $entity->adapter = 'Local';

        $file = $this->FileStorage->behaviors()->FileStorage->entityToFileObject($entity);

        $result = $this->FileStorage->behaviors()->FileStorage->processImages($file, $entity);

        $this->assertArrayHasKey('entityModel', $result->variants());
        $this->assertArrayNotHasKey('legacy', $result->variants());
    }

    /**
     * Regression: when the processor (or any post-store step) throws, the
     * behavior used to delete the DB row but leave the just-stored file on
     * the storage adapter. Verify the file is now also cleaned up.
     *
     * @return void
     */
    public function testAfterSaveRollbackRemovesStoredFileOnProcessorFailure(): void
    {
        $behaviorConfig = Configure::read('FileStorage.behaviorConfig');

        $throwingProcessor = new class implements ProcessorInterface {
            public function process(FileInterface $file): FileInterface
            {
                throw new RuntimeException('processor failure (test regression)');
            }
        };

        $table = $this->getTableLocator()->get('FileStorage.FileStorage', ['alias' => 'IsolatedFileStorage']);
        $table->removeBehavior('FileStorage');
        $table->addBehavior('FileStorage.FileStorage', [
            'fileStorage' => $behaviorConfig['fileStorage'],
            'fileProcessor' => $throwingProcessor,
        ]);

        $beforeRegularFiles = $this->countRegularFilesUnder($this->testPath);

        $entity = $table->newEntity([
            'model' => 'Document',
            'adapter' => 'Local',
            'file' => new UploadedFile(
                $this->fileFixtures . 'titus.jpg',
                filesize($this->fileFixtures . 'titus.jpg'),
                UPLOAD_ERR_OK,
                'titus.jpg',
                'image/jpeg',
            ),
        ]);

        $caught = null;
        try {
            $table->saveOrFail($entity);
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'Expected the processor failure to propagate');
        $this->assertSame('processor failure (test regression)', $caught->getMessage());
        $this->assertSame(0, $table->find()->where(['model' => 'Document'])->count(), 'Entity row should have been rolled back');

        $afterRegularFiles = $this->countRegularFilesUnder($this->testPath);
        $this->assertSame(
            $beforeRegularFiles,
            $afterRegularFiles,
            'Stored file should have been removed from disk as part of rollback (was leaving orphans)',
        );
    }

    /**
     * Helper for the rollback test: counts regular files under a directory
     * tree, ignoring dotfiles and directories.
     */
    protected function countRegularFilesUnder(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }
        $count = 0;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iter as $entry) {
            if ($entry->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Test processImages() when no config exists for model/collection
     *
     * @return void
     */
    public function testProcessImagesWithNoConfig()
    {
        $entity = $this->FileStorage->newEmptyEntity();
        $entity->collection = 'NonExistentCollection';
        $entity->id = 'test-id';
        $entity->path = 'test/path.jpg';
        $entity->adapter = 'Local';

        $file = $this->FileStorage->behaviors()->FileStorage->entityToFileObject($entity);

        $result = $this->FileStorage->behaviors()->FileStorage->processImages($file, $entity);

        $this->assertEmpty($result->variants());
    }
}
