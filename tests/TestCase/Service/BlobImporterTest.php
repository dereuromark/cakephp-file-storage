<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Database\Exception\QueryException;
use Cake\Event\EventInterface;
use Cake\Utility\Text;
use FileStorage\Exception\BlobHashMismatchException;
use FileStorage\Exception\BlobNotAvailableException;
use FileStorage\Service\BlobAttacher;
use FileStorage\Service\BlobImporter;
use FileStorage\Test\TestCase\FileStorageTestCase;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use PhpCollective\Infrastructure\Storage\File;
use PhpCollective\Infrastructure\Storage\FileStorage as Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Exercises registration of files already on an adapter.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobImporterTest extends FileStorageTestCase
{
    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    protected BlobImporter $importer;

    protected FilesystemAdapter $adapter;

    protected string $hash;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('FileStorage.imageVariants', []);
        Configure::write('FileStorage.deduplicate.collections', ['Items' => ['Photos' => true]]);
        $this->importer = new BlobImporter($this->FileStorage);
        $this->adapter = Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local');
        $this->adapter->write('temporary/source', 'stored content', new Config());
        $this->hash = hash('sha256', 'stored content');
    }

    public function tearDown(): void
    {
        Configure::delete('FileStorage.deduplicate');
        Configure::delete('FileStorage.hashAlgorithm');
        parent::tearDown();
    }

    protected function blobCount(): int
    {
        return (int)$this->FileStorage->getConnection()->execute('SELECT COUNT(*) FROM file_storage_blobs')->fetchColumn(0);
    }

    public function testMissHitAndMissingRegisteredFile(): void
    {
        $first = $this->importer->import('Local', 'temporary/source', 'photo.JPG');
        $this->assertStringStartsWith('blobs/', $first->path);
        $this->assertSame($this->hash, pathinfo($first->path, PATHINFO_FILENAME));
        $this->assertSame($this->hash, $first->hash);
        $this->assertSame('jpg', pathinfo($first->path, PATHINFO_EXTENSION));
        $this->assertSame('stored content', $this->adapter->read($first->path));
        $this->assertSame(1, $this->blobCount());
        $row = $this->FileStorage->getConnection()->execute('SELECT * FROM file_storage_blobs')->fetch('assoc');
        $this->assertSame($first->path, $row['path']);
        $this->assertSame($this->hash, $row['hash']);
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturn(true);
        $adapter->method('readStream')->willReturnCallback(function () {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, 'stored content');
            rewind($stream);

            return $stream;
        });
        $adapter->expects($this->never())->method('copy');
        $this->useAdapter($adapter);
        $second = $this->importer->import('Local', 'temporary/source', 'different.pdf');
        $this->assertEquals($first, $second);
        $this->useAdapter($this->adapter);
        $this->adapter->delete($first->path);
        $third = $this->importer->import('Local', 'temporary/source', 'different.pdf');
        $this->assertEquals($first, $third);
        $this->assertSame('stored content', $this->adapter->read($third->path));
    }

    protected function useAdapter(FilesystemAdapter $adapter): void
    {
        $storage = $this->createStub(Storage::class);
        $storage->method('getStorage')->willReturn($adapter);
        $real = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($real instanceof Storage) {
            $storage->method('buildPath')->willReturnCallback(fn ($file) => $real->buildPath($file));
        }
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);
    }

    public function testMismatchKeepsSource(): void
    {
        try {
            $this->importer->import('Local', 'temporary/source', 'a.txt', str_repeat('a', 64), ['deleteSource' => true]);
            $this->fail('Expected mismatch');
        } catch (BlobHashMismatchException) {
            $this->assertSame(0, $this->blobCount());
            $this->assertTrue($this->adapter->fileExists('temporary/source'));
        }
    }

    public function testTrustedHashSkipsReads(): void
    {
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturnCallback(fn ($path) => $path === 'temporary/source');
        $adapter->expects($this->never())->method('readStream');
        $adapter->expects($this->once())->method('copy')->willReturnCallback(function ($source, $destination, $config): void {
            $this->adapter->copy($source, $destination, $config);
        });
        $this->useAdapter($adapter);
        $trusted = str_repeat('a', 64);
        $claim = $this->importer->import('Local', 'temporary/source', 'a.txt', $trusted, ['verify' => false]);
        $this->assertSame($trusted, pathinfo($claim->path, PATHINFO_FILENAME));
        $this->assertSame('stored content', $this->adapter->read($claim->path));
        $this->assertSame(1, $this->blobCount());
    }

    public function testTrustedHashStillChecksUnregisteredDestination(): void
    {
        $trusted = str_repeat('b', 64);
        /** @var \PhpCollective\Infrastructure\Storage\FileStorage $storage */
        $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        $destination = $storage->buildPath(File::create('a.txt', 0, '', 'Local')->withUuid(Text::uuid())->withHash($trusted))->path();
        $this->adapter->write($destination, 'leftover', new Config());
        $message = null;
        try {
            $this->importer->import('Local', 'temporary/source', 'a.txt', $trusted, ['verify' => false, 'deleteSource' => true]);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }
        $this->assertSame('destination occupied', $message);
        $this->assertSame(0, $this->blobCount());
        $this->assertSame('leftover', $this->adapter->read($destination));
        $this->assertTrue($this->adapter->fileExists('temporary/source'));
    }

    public static function invalidProvider(): array
    {
        return [
            ['Local', 'temporary/source', null, ['verify' => false]],
            ['', 'temporary/source', null, []],
            ['Local', 'blobs/source', null, []],
            ['Local', './blobs/source', null, []],
            ['Local', '/blobs/source', null, []],
            ['Local', 'temporary/../source', null, []],
            ['Local', 'temporary\\..\\source', null, []],
            ['Local', 'temporary/source', 'bad', []],
            ['Local', 'temporary/source', str_repeat('A', 64), []],
            ['Local', 'temporary/source', str_repeat('a', 64) . "\n", []],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidInput(string $adapter, string $source, ?string $hash, array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->importer->import($adapter, $source, 'a.txt', $hash, $options);
    }

    public function testInvalidAlgorithm(): void
    {
        Configure::write('FileStorage.hashAlgorithm', 'sha1');
        $this->expectException(InvalidArgumentException::class);
        $this->importer->import('Local', 'temporary/source', 'a.txt');
    }

    public function testMissingSource(): void
    {
        $this->expectException(BlobNotAvailableException::class);
        $this->importer->import('Local', 'missing', 'a.txt');
    }

    public function testOccupiedDestination(): void
    {
        $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        $file = File::create('a.txt', 0, '', 'Local')
            ->withUuid(Text::uuid())->withHash($this->hash);
        $path = $storage->buildPath($file)->path();
        $this->adapter->write($path, 'other content', new Config());
        try {
            $this->importer->import('Local', 'temporary/source', 'a.txt');
            $this->fail('Expected occupied destination');
        } catch (RuntimeException $exception) {
            $this->assertSame('destination occupied', $exception->getMessage());
            $this->assertSame(0, $this->blobCount());
            $this->assertSame('other content', $this->adapter->read($path));
        }
    }

    public function testUnregisteredMatchingDestinationIsReused(): void
    {
        $first = $this->importer->import('Local', 'temporary/source', 'a.txt');
        $this->FileStorage->getConnection()->execute('DELETE FROM file_storage_blobs');
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturnCallback(fn ($path) => $this->adapter->fileExists($path));
        $adapter->method('readStream')->willReturnCallback(fn ($path) => $this->adapter->readStream($path));
        $adapter->expects($this->never())->method('copy');
        $this->useAdapter($adapter);
        $claim = $this->importer->import('Local', 'temporary/source', 'a.txt');
        $this->assertSame($first->path, $claim->path);
        $this->assertSame(1, $this->blobCount());
        $row = $this->FileStorage->getConnection()->execute('SELECT path FROM file_storage_blobs')->fetch('assoc');
        $this->assertSame($claim->path, $row['path']);
    }

    public function testReadBackFailureRemovesDestination(): void
    {
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturnCallback(fn ($path) => $this->adapter->fileExists($path));
        $adapter->method('readStream')->willReturnCallback(fn ($path) => $this->adapter->readStream($path));
        $adapter->expects($this->once())->method('copy')->willReturnCallback(function ($source, $destination, $config): void {
            $this->adapter->write($destination, 'corrupted', $config);
        });
        $adapter->expects($this->once())->method('delete')->willReturnCallback(fn ($path) => $this->adapter->delete($path));
        $this->useAdapter($adapter);
        try {
            $this->importer->import('Local', 'temporary/source', 'a.txt', null, ['deleteSource' => true]);
            $this->fail('Expected read-back mismatch');
        } catch (BlobHashMismatchException) {
            $this->assertSame(0, $this->blobCount());
            $this->assertTrue($this->adapter->fileExists('temporary/source'));
            $files = array_filter(iterator_to_array($this->adapter->listContents('blobs', true)), fn ($entry) => $entry->isFile());
            $this->assertSame([], $files);
        }
    }

    public function testRecordPathFailureCleansUpWhileLocked(): void
    {
        $connection = $this->FileStorage->getConnection();
        $connection->execute("CREATE TRIGGER reject_blob_path BEFORE UPDATE OF path ON file_storage_blobs BEGIN SELECT RAISE(ABORT, 'path rejected'); END");
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturnCallback(fn ($path) => $this->adapter->fileExists($path));
        $adapter->method('readStream')->willReturnCallback(fn ($path) => $this->adapter->readStream($path));
        $adapter->method('copy')->willReturnCallback(fn ($source, $destination, $config) => $this->adapter->copy($source, $destination, $config));
        $adapter->expects($this->once())->method('delete')->willReturnCallback(function ($path) use ($connection): void {
            $this->assertTrue($connection->inTransaction());
            $this->adapter->delete($path);
        });
        $this->useAdapter($adapter);
        try {
            $this->importer->import('Local', 'temporary/source', 'a.txt', null, ['deleteSource' => true]);
            $this->fail('Expected path update failure');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('path rejected', $exception->getMessage());
            $this->assertSame(0, $this->blobCount());
            $this->assertTrue($this->adapter->fileExists('temporary/source'));
        } finally {
            $connection->execute('DROP TRIGGER reject_blob_path');
        }
    }

    public function testEventDeletionAndAttachment(): void
    {
        $events = [];
        $listener = function (EventInterface $event) use (&$events): void {
            $this->assertFalse($this->FileStorage->getConnection()->inTransaction());
            $this->assertFalse($this->adapter->fileExists('temporary/source'));
            $events[] = $event->getData();
        };
        $this->FileStorage->getEventManager()->on('FileStorage.blobImported', $listener);
        try {
            $claim = $this->importer->import('Local', 'temporary/source', 'a.txt', null, ['deleteSource' => true]);
        } finally {
            $this->FileStorage->getEventManager()->off('FileStorage.blobImported', $listener);
        }
        $this->assertSame([['adapter' => 'Local', 'hash' => $this->hash, 'path' => $claim->path, 'sourcePath' => 'temporary/source', 'reused' => false]], $events);
        Configure::write('FileStorage.deduplicate.attachAuthorizer', static fn (): bool => true);
        $file = (new BlobAttacher($this->FileStorage))->attach($this->hash, ['model' => 'Items', 'collection' => 'Photos', 'filename' => 'a.txt']);
        $this->assertSame($claim->id, $file->blob_id);
        $this->assertSame($claim->path, $file->path);
    }

    public function testListenerExceptionIsIgnored(): void
    {
        $listener = static function (): void {
            throw new RuntimeException('listener failed');
        };
        $this->FileStorage->getEventManager()->on('FileStorage.blobImported', $listener);
        try {
            $claim = $this->importer->import('Local', 'temporary/source', 'a.txt');
            $this->assertNotNull($claim->path);
            $this->assertSame(1, $this->blobCount());
        } finally {
            $this->FileStorage->getEventManager()->off('FileStorage.blobImported', $listener);
        }
    }

    public function testDeleteFailureAndReusedEvent(): void
    {
        $first = $this->importer->import('Local', 'temporary/source', 'a.txt');
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturn(true);
        $adapter->expects($this->never())->method('readStream');
        $adapter->expects($this->never())->method('copy');
        $adapter->expects($this->once())->method('delete')->willThrowException(new RuntimeException('delete failed'));
        $this->useAdapter($adapter);
        $received = null;
        $listener = function (EventInterface $event) use (&$received): void {
            $received = $event->getData('reused');
        };
        $this->FileStorage->getEventManager()->on('FileStorage.blobImported', $listener);
        try {
            $claim = $this->importer->import('Local', 'temporary/source', 'a.txt', $this->hash, ['verify' => false, 'deleteSource' => true]);
            $this->assertEquals($first, $claim);
            $this->assertTrue($received);
            $this->assertTrue($this->adapter->fileExists('temporary/source'));
            $this->assertSame(1, $this->blobCount());
        } finally {
            $this->FileStorage->getEventManager()->off('FileStorage.blobImported', $listener);
        }
    }

    public function testOuterTransactionKeepsSource(): void
    {
        $connection = $this->FileStorage->getConnection();
        $connection->begin();
        try {
            $this->importer->import('Local', 'temporary/source', 'a.txt', null, ['deleteSource' => true]);
            $this->fail('Expected transaction rejection');
        } catch (RuntimeException) {
            $this->assertTrue($this->adapter->fileExists('temporary/source'));
            $this->assertSame(0, $this->blobCount());
        } finally {
            $connection->rollback();
        }
    }
}
