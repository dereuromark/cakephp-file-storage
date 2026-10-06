<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use FileStorage\Exception\BlobAttachDeniedException;
use FileStorage\Exception\BlobNotAvailableException;
use FileStorage\Model\Behavior\FileStorageBehavior;
use FileStorage\Model\Entity\FileStorage;
use FileStorage\Service\BlobAttacher;
use FileStorage\Test\TestCase\FileStorageTestCase;
use InvalidArgumentException;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToRetrieveMetadata;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests authorized attachment to uploaded blobs.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobAttacherTest extends FileStorageTestCase
{
    protected array $fixtures = [
        'plugin.FileStorage.FileStorageBlobs',
        'plugin.FileStorage.FileStorage',
    ];

    protected BlobAttacher $attacher;

    protected array $data = ['model' => 'Items', 'collection' => 'Photos', 'filename' => 'attached.png', 'user_id' => 42];

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('FileStorage.imageVariants', []);
        Configure::write('FileStorage.deduplicate.collections', ['Items' => ['Photos' => true]]);
        $this->attacher = new BlobAttacher($this->FileStorage);
    }

    public function tearDown(): void
    {
        Configure::delete('FileStorage.deduplicate');
        parent::tearDown();
    }

    protected function upload(): FileStorage
    {
        $entity = $this->FileStorage->newEntity([
            'model' => 'Items',
            'collection' => 'Photos',
            'user_id' => 42,
            'file' => new UploadedFile($this->fileFixtures . 'titus.jpg', filesize($this->fileFixtures . 'titus.jpg'), UPLOAD_ERR_OK, 'original.jpg', 'image/jpeg'),
        ]);
        $this->FileStorage->saveOrFail($entity);

        return $entity;
    }

    public function testDefaultDeny(): void
    {
        $original = $this->upload();
        $count = $this->FileStorage->find()->count();
        try {
            $this->attacher->attach($original->hash, $this->data);
            $this->fail('Expected denial');
        } catch (BlobAttachDeniedException) {
            $this->assertSame($count, $this->FileStorage->find()->count());
        }
    }

    public static function deniedProvider(): array
    {
        return [[false], [null], [1], ['yes'], ['not a closure']];
    }

    #[DataProvider('deniedProvider')]
    public function testDeniedAuthorizer(mixed $result): void
    {
        Configure::write('FileStorage.deduplicate.attachAuthorizer', $result === 'not a closure' ? 'strlen' : static fn () => $result);
        $this->testDefaultDeny();
    }

    public function testAttachAndDelete(): void
    {
        $original = $this->upload();
        $behavior = $this->FileStorage->getBehavior('FileStorage');
        $adapter = $behavior->getStorageAdapter('Local');
        $before = iterator_to_array($adapter->listContents('', true));
        $received = [];
        $context = ['userId' => 42];
        Configure::write('FileStorage.deduplicate.attachAuthorizer', function ($hash, $data, $context) use (&$received): bool {
            $received = [$hash, $data, $context];

            return true;
        });
        $events = [];
        $this->FileStorage->getEventManager()->on('FileStorage.blobAttached', function (EventInterface $event) use (&$events): void {
            $events[] = $event->getData('entity');
        });
        $data = $this->data + ['path' => 'evil', 'blob_id' => 999, 'id' => 999, 'uuid' => 'evil', 'variants' => ['evil']];
        $attached = $this->attacher->attach($original->hash, $data, $context);
        $this->assertSame([$original->hash, $data, $context], $received);
        $this->assertSame([$attached], $events);
        $this->assertNotSame($original->uuid, $attached->uuid);
        $this->assertNotSame($original->id, $attached->id);
        $this->assertNotSame(999, $attached->id);
        foreach (['path', 'blob_id', 'hash', 'filesize'] as $field) {
            $this->assertSame($original->get($field), $attached->get($field));
        }
        $this->assertSame('attached.png', $attached->filename);
        $this->assertSame('png', $attached->extension);
        $this->assertSame('image/jpeg', $attached->mime_type);
        $this->assertSame([], $attached->variants);
        $this->assertSame([], $attached->metadata);
        $this->assertSame($behavior, $this->FileStorage->getBehavior('FileStorage'));
        $this->assertEquals($before, iterator_to_array($adapter->listContents('', true)));
        $this->assertSame($attached->path, $this->FileStorage->get($attached->id)->path);
        $this->FileStorage->deleteOrFail($attached);
        $this->assertFileExists($this->testPath . $original->path);
        $this->assertSame($original->blob_id, $this->FileStorage->get($original->id)->blob_id);
        $this->assertTrue($this->attacher->has($original->hash));
    }

    public function testMimeTypeUnavailable(): void
    {
        $original = $this->upload();
        Configure::write('FileStorage.deduplicate.attachAuthorizer', static fn (): bool => true);
        $adapter = $this->createStub(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturn(true);
        $adapter->method('fileSize')->willReturn(new FileAttributes($original->path, $original->filesize));
        $adapter->method('mimeType')->willThrowException(UnableToRetrieveMetadata::mimeType($original->path));
        $behavior = $this->getMockBuilder(FileStorageBehavior::class)
            ->setConstructorArgs([$this->FileStorage, Configure::read('FileStorage.behaviorConfig')])
            ->onlyMethods(['getStorageAdapter'])
            ->getMock();
        $behavior->expects($this->exactly(2))->method('getStorageAdapter')->willReturn($adapter);
        $this->FileStorage->behaviors()->set('FileStorage', $behavior);
        $attached = $this->attacher->attach($original->hash, $this->data);
        $this->assertNull($attached->mime_type);
        $attached = $this->attacher->attach($original->hash, ['mime_type' => 'application/custom'] + $this->data);
        $this->assertSame('application/custom', $attached->mime_type);
    }

    public function testUnknownHashRollsBackClaim(): void
    {
        Configure::write('FileStorage.deduplicate.attachAuthorizer', static fn (): bool => true);
        $count = $this->FileStorage->find()->count();
        try {
            $this->attacher->attach(str_repeat('a', 64), $this->data);
            $this->fail('Expected unavailable blob');
        } catch (BlobNotAvailableException) {
            $this->assertSame(0, (int)$this->FileStorage->getConnection()->execute('SELECT COUNT(*) FROM file_storage_blobs')->fetchColumn(0));
            $this->assertSame($count, $this->FileStorage->find()->count());
        }
    }

    public function testMissingStoredFile(): void
    {
        $original = $this->upload();
        unlink($this->testPath . $original->path);
        Configure::write('FileStorage.deduplicate.attachAuthorizer', static fn (): bool => true);
        $this->expectException(BlobNotAvailableException::class);
        $this->attacher->attach($original->hash, $this->data);
    }

    public function testOptedOut(): void
    {
        Configure::write('FileStorage.deduplicate.attachAuthorizer', static fn (): bool => true);
        $this->expectException(BlobAttachDeniedException::class);
        $this->attacher->attach(str_repeat('a', 64), ['collection' => 'Private'] + $this->data);
    }

    public static function invalidProvider(): array
    {
        return [
            ['bad', ['model' => 'Items', 'filename' => 'a']],
            [str_repeat('A', 64), ['model' => 'Items', 'filename' => 'a']],
            [str_repeat('a', 64) . "\n", ['model' => 'Items', 'filename' => 'a']],
            [str_repeat('a', 64), ['filename' => 'a']],
            [str_repeat('a', 64), ['model' => 'Items']],
            [str_repeat('a', 64), ['model' => 'Items', 'filename' => '']],
            [str_repeat('a', 64), ['model' => 1, 'filename' => 'a']],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidInput(string $hash, array $data): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->attacher->attach($hash, $data);
    }

    public function testLookupHelpers(): void
    {
        $original = $this->upload();
        $this->assertTrue($this->attacher->has($original->hash));
        $this->assertFalse($this->attacher->has(str_repeat('a', 64)));
        $this->assertFalse($this->attacher->has($original->hash, 'Other'));
        $this->assertTrue($this->attacher->userOwnsHash(42, $original->hash));
        $this->assertTrue($this->attacher->userOwnsHash('42', $original->hash));
        $this->assertFalse($this->attacher->userOwnsHash(43, $original->hash));
        $this->assertFalse($this->attacher->userOwnsHash(42, str_repeat('a', 64)));
        $this->FileStorage->getConnection()->execute('UPDATE file_storage_blobs SET path = NULL');
        $this->assertFalse($this->attacher->has($original->hash));
    }
}
