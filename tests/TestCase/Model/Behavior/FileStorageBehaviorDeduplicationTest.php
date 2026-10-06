<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Behavior;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use FileStorage\FileStorage\DataTransformerInterface;
use FileStorage\Model\Behavior\FileStorageBehavior;
use FileStorage\Model\Entity\FileStorage;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;
use PhpCollective\Infrastructure\Storage\FileInterface;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;

class FileStorageBehaviorDeduplicationTest extends FileStorageTestCase
{
    protected array $fixtures = [
        'plugin.FileStorage.FileStorageBlobs',
        'plugin.FileStorage.FileStorage',
    ];

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('FileStorage.imageVariants.Items.Photos.thumbnail', [
            'operations' => ['resize' => ['width' => 50, 'height' => 50]],
        ]);
        Configure::write('FileStorage.deduplicate.collections', ['Items' => ['Photos' => true]]);
        $this->FileStorage->removeBehavior('FileStorage');
        $this->FileStorage->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
    }

    public function tearDown(): void
    {
        Configure::delete('FileStorage.deduplicate');
        Configure::delete('FileStorage.hashAlgorithm');
        parent::tearDown();
    }

    protected function upload(string $filename = 'titus.jpg', string $collection = 'Photos'): FileStorage
    {
        return $this->FileStorage->newEntity([
            'model' => 'Items',
            'collection' => $collection,
            'file' => new UploadedFile(
                $this->fileFixtures . 'titus.jpg',
                filesize($this->fileFixtures . 'titus.jpg'),
                UPLOAD_ERR_OK,
                $filename,
                'image/jpeg',
            ),
        ]);
    }

    protected function blobCount(): int
    {
        return (int)$this->FileStorage->getConnection()->execute('SELECT COUNT(*) FROM file_storage_blobs')->fetchColumn(0);
    }

    public function testSaveWithoutUploadStillStops(): void
    {
        $count = $this->FileStorage->find()->count();
        $entity = $this->FileStorage->newEntity(['model' => 'Items', 'collection' => 'Photos', 'filename' => 'no-upload.jpg']);
        $this->assertFalse($this->FileStorage->save($entity));
        $this->assertSame($count, $this->FileStorage->find()->count());
    }

    public function testReuseAndRestoreAtFirstExtensionPath(): void
    {
        $events = [];
        foreach (['beforeStoringFile', 'afterStoringFile', 'blobReused'] as $name) {
            EventManager::instance()->on('FileStorage.' . $name, function (EventInterface $event) use (&$events): void {
                $events[] = $event->getName();
                if ($event->getName() === 'FileStorage.blobReused') {
                    $this->assertStringStartsWith('blobs/', $event->getData('file')->path());
                }
            });
        }

        $first = $this->upload();
        $this->FileStorage->saveOrFail($first);
        $this->assertTrue($first->isDeduplicated());
        $this->assertStringStartsWith('blobs/', $first->path);
        $this->FileStorage->getConnection()->execute("UPDATE file_storage_blobs SET touched = '2000-01-01 00:00:00'");
        $this->assertSame(['FileStorage.beforeStoringFile', 'FileStorage.afterStoringFile'], $events);
        $events = [];
        $second = $this->upload('duplicate.png');
        $this->FileStorage->saveOrFail($second);
        $this->assertSame($first->path, $second->path);
        $this->assertSame($first->blob_id, $second->blob_id);
        $this->assertNotSame($first->uuid, $second->uuid);
        $this->assertSame(['FileStorage.blobReused'], $events);
        $touched = $this->FileStorage->getConnection()->execute('SELECT touched FROM file_storage_blobs')->fetchColumn(0);
        $this->assertNotSame('2000-01-01 00:00:00', $touched);
        $files = iterator_to_array($this->FileStorage->getBehavior('FileStorage')->getStorageAdapter('Local')->listContents('blobs', true));
        $this->assertCount(1, array_filter($files, static fn ($file): bool => $file->isFile()));
        unlink($this->testPath . $first->path);
        $third = $this->upload('restored.gif');
        $this->FileStorage->saveOrFail($third);
        $this->assertSame($first->path, $third->path);
        $this->assertSame(hash_file('sha256', $this->fileFixtures . 'titus.jpg'), hash_file('sha256', $this->testPath . $third->path));
        $this->assertSame(1, $this->blobCount());
        foreach (['beforeStoringFile', 'afterStoringFile', 'blobReused'] as $name) {
            EventManager::instance()->off('FileStorage.' . $name);
        }
    }

    public function testDeleteKeepsSharedBlobAndOtherVariants(): void
    {
        $first = $this->upload();
        $second = $this->upload();
        $this->FileStorage->saveOrFail($first);
        $this->FileStorage->saveOrFail($second);
        $variant = $first->variants['thumbnail']['path'];
        $this->assertFileExists($this->testPath . $variant);
        $this->FileStorage->deleteOrFail($first);
        $this->assertFileDoesNotExist($this->testPath . $variant);
        $this->assertFileExists($this->testPath . $second->variants['thumbnail']['path']);
        $this->assertFileExists($this->testPath . $second->path);
        $this->assertSame(1, $this->blobCount());
        unlink($this->testPath . $second->variants['thumbnail']['path']);
        $this->FileStorage->deleteOrFail($second);
        $this->assertFileExists($this->testPath . $second->path);
        $this->assertSame(1, $this->blobCount());
    }

    public function testPrivateHashFilenameAndOptedOutReplacement(): void
    {
        $first = $this->upload();
        $this->FileStorage->saveOrFail($first);
        $private = $this->upload($first->hash . '.jpg', 'Private');
        $this->FileStorage->saveOrFail($private);
        $this->assertNull($private->blob_id);
        $this->assertFalse($private->isDeduplicated());
        $this->assertStringContainsString(str_replace('-', '', $private->uuid), $private->path);
        $this->FileStorage->deleteOrFail($private);
        $this->assertFileDoesNotExist($this->testPath . $private->path);
        $oldPath = $first->path;
        Configure::write('FileStorage.deduplicate.collections', false);
        $replacement = $this->upload();
        $first = $this->FileStorage->patchEntity($first, ['file' => $replacement->file]);
        $this->FileStorage->saveOrFail($first);
        $this->assertNull($this->FileStorage->get($first->id)->blob_id);
        $this->assertNotSame($oldPath, $first->path);
        $this->assertFileExists($this->testPath . $oldPath);
    }

    protected function failProcessing(): void
    {
        $config = Configure::read('FileStorage.behaviorConfig');
        $config['fileProcessor'] = new class implements ProcessorInterface {
            public function process(FileInterface $file): FileInterface
            {
                throw new RuntimeException('processor failure');
            }
        };
        $this->FileStorage->removeBehavior('FileStorage');
        $this->FileStorage->addBehavior('FileStorage.FileStorage', $config);
    }

    public function testFailedFirstUploadRollsBackRowsAndKeepsBlob(): void
    {
        $this->failProcessing();
        $entity = $this->upload();
        $count = $this->FileStorage->find()->count();
        try {
            $this->FileStorage->saveOrFail($entity);
            $this->fail('Expected processor failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('processor failure', $exception->getMessage());
        }
        $this->assertSame($count, $this->FileStorage->find()->count());
        $this->assertSame(0, $this->blobCount());
        $this->assertFileExists($this->testPath . $entity->path);
        $this->assertTrue($this->FileStorage->hasBehavior('FileStorage'));
    }

    public function testMetadataFailureRemovesNewVariantsAndRestoresBehavior(): void
    {
        $entity = $this->upload();
        $count = $this->FileStorage->find()->count();
        $listener = function (EventInterface $event, FileStorage $saving): void {
            if ($saving->blob_id !== null) {
                throw new RuntimeException('metadata failure');
            }
        };
        $this->FileStorage->getEventManager()->on('Model.beforeSave', $listener);
        try {
            $this->FileStorage->saveOrFail($entity);
            $this->fail('Expected metadata failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('metadata failure', $exception->getMessage());
        } finally {
            $this->FileStorage->getEventManager()->off('Model.beforeSave', $listener);
        }
        $this->assertSame($count, $this->FileStorage->find()->count());
        $this->assertSame(0, $this->blobCount());
        $this->assertFileExists($this->testPath . $entity->path);
        $this->assertFileDoesNotExist($this->testPath . $entity->variants['thumbnail']['path']);
        $this->assertTrue($this->FileStorage->hasBehavior('FileStorage'));
        $this->FileStorage->saveOrFail($this->upload());
    }

    public function testReplacementAndFailedReplacement(): void
    {
        $first = $this->upload();
        $second = $this->upload();
        $this->FileStorage->saveOrFail($first);
        $this->FileStorage->saveOrFail($second);
        $source = $this->testPath . 'replacement.jpg';
        copy($this->fileFixtures . 'titus.jpg', $source);
        file_put_contents($source, 'changed', FILE_APPEND);
        $upload = new UploadedFile($source, filesize($source), UPLOAD_ERR_OK, 'titus.jpg', 'image/jpeg');
        $first = $this->FileStorage->patchEntity($first, ['file' => $upload]);
        $this->FileStorage->saveOrFail($first);
        $this->assertNotSame($first->blob_id, $second->blob_id);
        $this->assertFileExists($this->testPath . $second->path);
        $previous = $this->FileStorage->get($first->id);
        $this->failProcessing();
        $first = $this->FileStorage->patchEntity($first, ['file' => $this->upload()->file]);
        try {
            $this->FileStorage->saveOrFail($first);
            $this->fail('Expected processor failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('processor failure', $exception->getMessage());
        }
        $restored = $this->FileStorage->get($first->id);
        foreach (['blob_id', 'path', 'hash'] as $field) {
            $this->assertSame($previous->get($field), $restored->get($field));
        }
        $this->assertFileExists($this->testPath . $previous->path);
        $this->assertFileExists($this->testPath . $previous->variants['thumbnail']['path']);
    }

    /**
     * @return array<string, array{mixed, string|null, string|null, bool}>
     */
    public static function configProvider(): array
    {
        return [
            'off' => [false, 'Items', 'Photos', false],
            'global' => [true, null, null, true],
            'empty' => [[], 'Items', 'Photos', false],
            'model' => [['Items' => true], 'Items', null, true],
            'other model' => [['Items' => true], 'Other', 'Photos', false],
            'collection' => [['Items' => ['Photos' => true]], 'Items', 'Photos', true],
            'other collection' => [['Items' => ['Photos' => true]], 'Items', 'Private', false],
            'no collection' => [['Items' => ['Photos' => true]], 'Items', null, false],
            'no model' => [['Items' => true], null, 'Photos', false],
            'collection false' => [['Items' => ['Photos' => false]], 'Items', 'Photos', false],
        ];
    }

    #[DataProvider('configProvider')]
    public function testConfig(mixed $config, ?string $model, ?string $collection, bool $expected): void
    {
        Configure::write('FileStorage.deduplicate.collections', $config);
        $method = new ReflectionMethod(FileStorageBehavior::class, 'isDeduplicated');
        $this->assertSame($expected, $method->invoke($this->FileStorage->getBehavior('FileStorage'), $model, $collection));
    }

    /**
     * @return array<string, array{mixed, bool, string}>
     */
    public static function preconditionProvider(): array
    {
        return [
            'non atomic' => ['sha256', false, 'atomic'],
            'wrong algorithm' => ['sha1', true, 'sha256'],
        ];
    }

    #[DataProvider('preconditionProvider')]
    public function testPreconditions(mixed $algorithm, bool $atomic, string $message): void
    {
        Configure::write('FileStorage.hashAlgorithm', $algorithm);
        $count = $this->FileStorage->find()->count();
        try {
            $this->FileStorage->save($this->upload(), ['atomic' => $atomic]);
            $this->fail('Expected precondition failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
        $this->assertSame($count, $this->FileStorage->find()->count());
        $this->assertSame(0, $this->blobCount());
    }

    public function testMissingHash(): void
    {
        $entity = $this->upload();
        $entity->file = ['tmp_name' => '/missing/upload', 'error' => UPLOAD_ERR_OK];
        $count = $this->FileStorage->find()->count();
        try {
            $this->FileStorage->save($entity);
            $this->fail('Expected missing hash failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('content hash', $exception->getMessage());
        }
        $this->assertSame($count, $this->FileStorage->find()->count());
    }

    public function testFileWithoutContentHashInterface(): void
    {
        $transformer = $this->createStub(DataTransformerInterface::class);
        $transformer->method('entityToFileObject')->willReturn($this->createStub(FileInterface::class));
        $this->FileStorage->getBehavior('FileStorage')->setConfig('dataTransformer', $transformer);
        $count = $this->FileStorage->find()->count();
        try {
            $this->FileStorage->save($this->upload());
            $this->fail('Expected interface failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ContentHashInterface', $exception->getMessage());
        }
        $this->assertSame($count, $this->FileStorage->find()->count());
        $this->assertSame(0, $this->blobCount());
    }

    public function testUnsupportedDriverBeforeRowWrite(): void
    {
        $original = $this->FileStorage->getConnection();
        $connection = $this->createStub(Connection::class);
        $connection->method('inTransaction')->willReturn(true);
        $connection->method('getDriver')->willReturn($this->createStub(Driver::class));
        $entity = $this->upload();
        $count = $this->FileStorage->find()->count();
        $listener = function () use ($connection): void {
            $this->FileStorage->setConnection($connection);
        };
        $this->FileStorage->getEventManager()->on('Model.beforeSave', ['priority' => 1], $listener);
        try {
            $this->FileStorage->save($entity);
            $this->fail('Expected unsupported driver failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unsupported', $exception->getMessage());
        } finally {
            $this->FileStorage->setConnection($original);
            $this->FileStorage->getEventManager()->off('Model.beforeSave', $listener);
        }
        $this->assertSame($count, $this->FileStorage->find()->count());
    }

    public function testPathOutsideRoot(): void
    {
        Configure::write('FileStorage.deduplicate.root', 'different-root');
        $count = $this->FileStorage->find()->count();
        try {
            $this->FileStorage->save($this->upload());
            $this->fail('Expected root failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('outside', $exception->getMessage());
        }
        $this->assertSame($count, $this->FileStorage->find()->count());
        $this->assertSame(0, $this->blobCount());
    }
}
