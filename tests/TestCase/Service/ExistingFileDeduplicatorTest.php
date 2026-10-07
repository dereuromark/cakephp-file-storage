<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Schema\CollectionInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Closure;
use FileStorage\Model\Entity\FileStorage;
use FileStorage\Service\BlobRegistry;
use FileStorage\Service\ExistingFileDeduplicator;
use FileStorage\Service\ExistingFileReport;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use PhpCollective\Infrastructure\Storage\File;
use PhpCollective\Infrastructure\Storage\FileStorage as Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Exercises conversion with real legacy uploads and local files.
 *
 * @author Mark Scherer
 * @license MIT
 */
class ExistingFileDeduplicatorTest extends FileStorageTestCase
{
    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    public function setUp(): void
    {
        parent::setUp();
        $this->FileStorage = $this->fetchTable('FileStorage.FileStorage');
        $this->FileStorage->deleteAll([]);
        Configure::write('FileStorage.deduplicate.collections', false);
        Configure::write('FileStorage.imageVariants.Items.Photos.thumbnail', ['operations' => ['resize' => ['width' => 50, 'height' => 50]]]);
        $this->FileStorage->removeBehavior('FileStorage');
        $this->FileStorage->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
    }

    public function tearDown(): void
    {
        Configure::delete('FileStorage.deduplicate');
        Configure::delete('FileStorage.hashAlgorithm');
        parent::tearDown();
    }

    protected function legacy(string $filename = 'titus.jpg', string $collection = 'Photos'): FileStorage
    {
        Configure::write('FileStorage.deduplicate.collections', false);
        $entity = $this->FileStorage->newEntity([
            'model' => 'Items',
            'collection' => $collection,
            'foreign_key' => '1',
            'file' => new UploadedFile($this->fileFixtures . 'titus.jpg', filesize($this->fileFixtures . 'titus.jpg'), UPLOAD_ERR_OK, $filename, 'image/jpeg'),
        ]);
        $this->FileStorage->saveOrFail($entity);
        Configure::write('FileStorage.deduplicate.collections', ['Items' => ['Photos' => true]]);

        return $this->FileStorage->get($entity->id);
    }

    protected function runService(array $options = []): ExistingFileReport
    {
        return (new ExistingFileDeduplicator())->run(null, null, $options);
    }

    protected function destination(FileStorage $row): string
    {
        $file = File::create($row->filename, $row->filesize, $row->mime_type, $row->adapter, $row->collection, $row->model, (string)$row->foreign_key);
        $file = $file->withUuid($row->uuid)->withHash(hash_file('sha256', $this->testPath . $row->path));

        return Configure::read('FileStorage.behaviorConfig.fileStorage')->buildPath($file)->path();
    }

    protected function keepSource(FileStorage $row, string $bytes): void
    {
        $this->assertSame($bytes, file_get_contents($this->testPath . $row->path));
        foreach ($row->variants as $variant) {
            $this->assertFileExists($this->testPath . $variant['path']);
        }
    }

    protected function noTemporaryFiles(): void
    {
        $adapter = Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local');
        foreach ($adapter->listContents('blobs/.tmp', true) as $file) {
            $this->assertFalse($file->isFile(), $file->path());
        }
    }

    public function testConvertsDuplicatesAndPreservesVariantsAndModified(): void
    {
        $first = $this->legacy();
        $second = $this->legacy('second.png');
        $third = $this->legacy('third.jpg');
        file_put_contents($this->testPath . $third->path, 'distinct content');
        $bytes = file_get_contents($this->testPath . $first->path);
        $this->FileStorage->updateAll(['hash' => 'wrong'], ['id' => $first->id]);
        $events = [];
        $listener = function (EventInterface $event) use (&$events): void {
            $this->assertSame($this->FileStorage, $event->getSubject());
            $this->assertFalse($this->FileStorage->getConnection()->inTransaction());
            $events[] = $event->getData();
        };
        $this->FileStorage->getEventManager()->on('FileStorage.blobConverted', $listener);
        try {
            $report = $this->runService();
        } finally {
            $this->FileStorage->getEventManager()->off('FileStorage.blobConverted', $listener);
        }
        $this->assertSame(3, $report->converted);
        $this->assertSame(1, $report->reusedExisting);
        $this->assertSame(3, $report->oldFilesKept);
        $this->assertSame(strlen($bytes) * 2 + strlen('distinct content'), $report->bytesKept);
        $this->assertSame(0, $report->failures);
        $fresh = $this->FileStorage->get($first->id);
        $duplicate = $this->FileStorage->get($second->id);
        $different = $this->FileStorage->get($third->id);
        $this->assertSame($fresh->blob_id, $duplicate->blob_id);
        $this->assertSame($fresh->path, $duplicate->path);
        $this->assertNotSame($fresh->blob_id, $different->blob_id);
        $this->assertSame(hash('sha256', $bytes), $fresh->hash);
        $this->assertEquals($first->modified, $fresh->modified);
        $this->assertSame($first->variants, $fresh->variants);
        $this->assertCount(3, $events);
        $this->assertSame(['id' => $first->id, 'oldPath' => $first->path, 'path' => $fresh->path, 'adapter' => 'Local', 'hash' => $fresh->hash], $events[0]);
        $this->keepSource($first, $bytes);
        $this->keepSource($second, $bytes);
        $this->keepSource($third, 'distinct content');
        $this->assertSame(0, $this->runService()->converted);
        $this->noTemporaryFiles();
    }

    public function testHashOnly(): void
    {
        $first = $this->legacy();
        $second = $this->legacy();
        $missing = $this->legacy();
        $this->FileStorage->updateAll(['hash' => null], ['id IN' => [$first->id, $missing->id]]);
        unlink($this->testPath . $missing->path);
        Configure::write('FileStorage.hashAlgorithm', 'sha1');
        Configure::write('FileStorage.deduplicate.collections', false);
        $bytes = file_get_contents($this->testPath . $first->path);
        $report = $this->runService(['hashOnly' => true]);
        $this->assertSame(1, $report->hashed);
        $this->assertSame(1, $report->missingFiles);
        $this->assertSame(0, $report->converted);
        $fresh = $this->FileStorage->get($first->id);
        $this->assertSame(hash('sha1', $bytes), $fresh->hash);
        $this->assertSame($first->path, $fresh->path);
        $this->assertNull($fresh->blob_id);
        $this->assertEquals($first->modified, $fresh->modified);
        $this->assertSame($second->hash, $this->FileStorage->get($second->id)->hash);
        $this->keepSource($first, $bytes);
        $this->noTemporaryFiles();
    }

    #[DataProvider('occupiedProvider')]
    public function testOccupiedDestination(bool $registered, bool $correct): void
    {
        $row = $this->legacy();
        $bytes = file_get_contents($this->testPath . $row->path);
        $destination = $this->destination($row);
        $adapter = Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local');
        $stored = $correct ? $bytes : 'unrelated bytes';
        $adapter->write($destination, $stored, new Config());
        if ($registered) {
            $connection = $this->FileStorage->getConnection();
            $connection->transactional(function () use ($row, $destination): void {
                $registry = new BlobRegistry($this->FileStorage);
                $claim = $registry->claim('Local', $row->hash, DateTime::now());
                $registry->recordPath($claim->id, $destination);
            });
        }
        $report = $this->runService();
        $this->assertSame($correct ? 1 : 0, $report->converted);
        $this->assertSame($correct ? 1 : 0, $report->reusedExisting);
        $this->assertSame($correct ? 0 : 1, $report->failures);
        if (!$correct) {
            $this->assertStringContainsString($registered ? 'registered blob has wrong content' : 'destination occupied', $report->failureSamples[0]);
            $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
        }
        $this->assertSame($stored, file_get_contents($this->testPath . $destination));
        $this->keepSource($row, $bytes);
        $this->noTemporaryFiles();
    }

    public static function occupiedProvider(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    public function testReusesUploadedBlobAndRestoresMissingRegisteredBlob(): void
    {
        $row = $this->legacy();
        $bytes = file_get_contents($this->testPath . $row->path);
        $upload = $this->FileStorage->newEntity(['model' => 'Items', 'collection' => 'Photos', 'file' => new UploadedFile($this->fileFixtures . 'titus.jpg', strlen($bytes), UPLOAD_ERR_OK, 'upload.png', 'image/jpeg')]);
        $this->FileStorage->saveOrFail($upload);
        $this->assertSame(1, $this->runService()->reusedExisting);
        $this->assertSame($upload->path, $this->FileStorage->get($row->id)->path);
        $this->keepSource($row, $bytes);
        $other = $this->legacy();
        unlink($this->testPath . $upload->path);
        $report = $this->runService();
        $this->assertSame(1, $report->converted);
        $this->assertSame(0, $report->reusedExisting);
        $this->assertSame($bytes, file_get_contents($this->testPath . $upload->path));
        $this->keepSource($other, $bytes);
    }

    public function testDryRunLimitAndSkips(): void
    {
        $first = $this->legacy();
        $second = $this->legacy();
        $private = $this->legacy('private.jpg', 'Private');
        $reserved = $this->legacy();
        $this->FileStorage->updateAll(['path' => 'blobs/legacy.jpg'], ['id' => $reserved->id]);
        $this->FileStorage->updateAll(['hash' => null], ['id' => $second->id]);
        $bytes = file_get_contents($this->testPath . $first->path);
        $report = $this->runService(['dryRun' => true]);
        $this->assertSame(2, $report->candidates);
        $this->assertSame(strlen($bytes) * 2, $report->candidateBytes);
        $this->assertSame(1, $report->withoutHash);
        $this->assertSame(0, $report->estimatedDuplicates);
        $this->assertSame(1, $report->skipped[ExistingFileDeduplicator::SKIP_NOT_ENABLED]);
        $this->assertSame(1, $report->skipped[ExistingFileDeduplicator::SKIP_BLOB_ROOT]);
        $this->assertStringContainsString('1 rows are not converted because the blob directory is reserved for blobs.', implode(' ', $report->warnings));
        $this->assertSame($first->path, $this->FileStorage->get($first->id)->path);
        $this->assertNull($this->FileStorage->get($second->id)->hash);
        $report = $this->runService(['dryRun' => true, 'limit' => 1]);
        $this->assertSame(1, $report->candidates);
        $report = $this->runService(['limit' => 1]);
        $this->assertSame(1, $report->converted);
        $this->assertSame($second->path, $this->FileStorage->get($second->id)->path);
        $this->keepSource($first, $bytes);
        $this->keepSource($private, $bytes);
    }

    public function testDatabaseDuplicateEstimate(): void
    {
        $first = $this->legacy();
        $this->legacy();
        $this->assertSame(1, $this->runService(['dryRun' => true])->estimatedDuplicates);
        $this->runService(['limit' => 1]);
        $this->assertSame(1, $this->runService(['dryRun' => true])->estimatedDuplicates);
        $this->keepSource($first, file_get_contents($this->fileFixtures . 'titus.jpg'));
    }

    public function testManifestAndThrowingListener(): void
    {
        $row = $this->legacy();
        $bytes = file_get_contents($this->testPath . $row->path);
        $manifest = $this->testPath . 'manifest.tsv';
        file_put_contents($manifest, 'existing line' . "\n");
        $listener = static function (): void {
            throw new RuntimeException('listener failure');
        };
        $this->FileStorage->getEventManager()->on('FileStorage.blobConverted', $listener);
        try {
            $report = $this->runService(['manifest' => $manifest]);
        } finally {
            $this->FileStorage->getEventManager()->off('FileStorage.blobConverted', $listener);
        }
        $fresh = $this->FileStorage->get($row->id);
        $this->assertSame(1, $report->converted);
        $this->assertSame(0, $report->failures);
        $this->assertStringContainsString('listener failure', implode(' ', $report->warnings));
        $this->assertSame('existing line' . "\n" . implode("\t", [$row->id, 'Local', $row->path, $fresh->path, $fresh->hash]) . "\n", file_get_contents($manifest));
        $this->keepSource($row, $bytes);
    }

    public function testUnwritableManifestFailsBeforeTouchingRows(): void
    {
        $row = $this->legacy();
        try {
            $this->runService(['manifest' => $this->testPath . 'missing/manifest.tsv']);
            $this->fail('Expected manifest precondition failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('manifest', $exception->getMessage());
        }
        $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
        $this->assertSame(0, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
    }

    protected function wrapAdapter(FilesystemAdapter $adapter): void
    {
        $storage = $this->createStub(Storage::class);
        $original = Configure::read('FileStorage.behaviorConfig.fileStorage');
        $storage->method('getStorage')->willReturn($adapter);
        $storage->method('buildPath')->willReturnCallback(fn ($file) => $original->buildPath($file));
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);
    }

    #[DataProvider('failureProvider')]
    public function testStorageFailures(string $failure): void
    {
        $row = $this->legacy();
        $bytes = file_get_contents($this->testPath . $row->path);
        $destination = $this->destination($row);
        $local = Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local');
        $adapter = $this->createStub(FilesystemAdapter::class);
        foreach (['fileExists', 'delete', 'fileSize'] as $method) {
            $adapter->method($method)->willReturnCallback(fn (...$args) => $local->$method(...$args));
        }
        $adapter->method('readStream')->willReturnCallback(function ($path) use ($local, $failure, $destination) {
            if (($failure === 'temporary mismatch' && str_contains($path, '/.tmp/')) || ($failure === 'destination mismatch' && $path === $destination)) {
                $stream = fopen('php://temp', 'w+b');
                fwrite($stream, 'wrong bytes');
                rewind($stream);

                return $stream;
            }

            return $local->readStream($path);
        });
        $adapter->method('writeStream')->willReturnCallback(function ($path, $stream, $config) use ($local, $failure): void {
            $local->writeStream($path, $stream, $config);
            if ($failure === 'write') {
                throw new RuntimeException('write failure');
            }
        });
        $adapter->method('move')->willReturnCallback(function ($source, $destination, $config) use ($local, $failure): void {
            $local->move($source, $destination, $config);
            if ($failure === 'move') {
                throw new RuntimeException('move failure');
            }
        });
        $this->wrapAdapter($adapter);
        $report = $this->runService();
        $this->assertSame(0, $report->converted);
        $this->assertSame(1, $report->failures);
        $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
        $this->assertNull($this->FileStorage->get($row->id)->blob_id);
        $this->assertFileDoesNotExist($this->testPath . $destination);
        $this->assertSame(0, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
        foreach ($local->listContents('blobs/.tmp', true) as $file) {
            $this->assertFalse($file->isFile());
        }
        $this->keepSource($row, $bytes);
    }

    public static function failureProvider(): array
    {
        return [['write'], ['move'], ['temporary mismatch'], ['destination mismatch']];
    }

    public function testSizeMismatchAndRunContinues(): void
    {
        $row = $this->legacy();
        $second = $this->legacy();
        $bytes = file_get_contents($this->testPath . $row->path);
        $local = Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local');
        $adapter = $this->createStub(FilesystemAdapter::class);
        foreach (['fileExists', 'readStream', 'writeStream', 'move', 'delete'] as $method) {
            $adapter->method($method)->willReturnCallback(fn (...$args) => $local->$method(...$args));
        }
        $adapter->method('fileSize')->willReturnCallback(fn ($path) => $path === $row->path ? new FileAttributes($path, 1) : $local->fileSize($path));
        $this->wrapAdapter($adapter);
        $report = $this->runService();
        $this->assertSame(1, $report->failures);
        $this->assertSame(1, $report->converted);
        $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
        $this->keepSource($row, $bytes);
        $this->keepSource($second, $bytes);
    }

    public function testBadHashPathTemplateStopsRun(): void
    {
        $row = $this->legacy();
        $storage = $this->createStub(Storage::class);
        $storage->method('getStorage')->willReturn(Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local'));
        $storage->method('buildPath')->willReturnCallback(fn ($file) => $file->withPath('blobs/not-the-hash.jpg'));
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);
        try {
            $this->runService();
            $this->fail('Expected configuration error');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('hashPathTemplate', $exception->getMessage());
        }
        $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
        $this->assertFalse($this->FileStorage->getConnection()->inTransaction());
        $this->noTemporaryFiles();
    }

    #[DataProvider('algorithmProvider')]
    public function testAlgorithmPreconditions(mixed $algorithm, bool $hashOnly): void
    {
        $row = $this->legacy();
        Configure::write('FileStorage.hashAlgorithm', $algorithm);
        try {
            $this->runService(['hashOnly' => $hashOnly]);
            $this->fail('Expected algorithm precondition failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('hash', $exception->getMessage());
        }
        $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
    }

    public static function algorithmProvider(): array
    {
        return [[false, true], ['invalid', true], ['sha512', true], ['sha1', false]];
    }

    public function testRowChangedBetweenListingAndLocking(): void
    {
        $row = $this->legacy();
        $bytes = file_get_contents($this->testPath . $row->path);
        $mutation = fn () => $this->FileStorage->updateAll(['adapter' => 'Changed'], ['id' => $row->id]);
        $service = new class ($mutation) extends ExistingFileDeduplicator {
            public function __construct(protected Closure $mutation)
            {
            }

            protected function rows(SelectQuery $query): iterable
            {
                foreach (parent::rows($query) as $row) {
                    ($this->mutation)();

                    yield $row;
                }
            }
        };
        $report = $service->run();
        $this->assertSame(0, $report->converted);
        $this->assertSame(1, $report->skipped[ExistingFileDeduplicator::SKIP_CHANGED]);
        $this->assertSame(0, $report->failures);
        $this->keepSource($row, $bytes);
    }

    public function testHighWaterExcludesNewRow(): void
    {
        $row = $this->legacy();
        $bytes = file_get_contents($this->testPath . $row->path);
        $added = null;
        $listener = function () use (&$added): void {
            $added = $this->legacy('later.jpg');
        };
        $this->FileStorage->getEventManager()->on('FileStorage.blobConverted', $listener);
        try {
            $report = $this->runService();
        } finally {
            $this->FileStorage->getEventManager()->off('FileStorage.blobConverted', $listener);
        }
        $this->assertSame(1, $report->converted);
        $this->assertNotSame(null, $added);
        $this->assertNull($this->FileStorage->get($added->id)->blob_id);
        $this->assertSame(1, $this->runService()->converted);
        $this->keepSource($row, $bytes);
        $this->keepSource($added, $bytes);
    }

    public function testKeysetBatchesAndBoundedMissingSamples(): void
    {
        $row = $this->legacy();
        $count = ExistingFileDeduplicator::BATCH_SIZE + 2;
        for ($index = 0; $index < $count; $index++) {
            $this->FileStorage->insertQuery()->insert(['uuid', 'filename', 'model', 'collection', 'adapter', 'path'])
                ->values(['uuid' => sprintf('10000000-0000-4000-8000-%012d', $index + 100), 'filename' => 'missing.jpg', 'model' => 'Items', 'collection' => 'Photos', 'adapter' => 'Local', 'path' => 'missing/' . $index . '.jpg'])
                ->execute();
        }
        $report = $this->runService();
        $this->assertSame(1, $report->converted);
        $this->assertSame($count, $report->missingFiles);
        $this->assertCount(50, $report->missingSamples);
        $this->assertSame(0, $report->failures);
        $this->keepSource($row, file_get_contents($this->fileFixtures . 'titus.jpg'));
    }

    public function testDryRunNeverReadsStorageOrCreatesManifest(): void
    {
        $this->legacy();
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->expects($this->never())->method('readStream');
        $adapter->expects($this->never())->method('writeStream');
        $this->wrapAdapter($adapter);
        $manifest = $this->testPath . 'dry-run.tsv';
        $report = $this->runService(['dryRun' => true, 'manifest' => $manifest]);
        $this->assertSame(1, $report->candidates);
        $this->assertFileDoesNotExist($manifest);
    }

    public function testHashOnlyConditionalSourceUpdate(): void
    {
        $row = $this->legacy();
        $this->FileStorage->updateAll(['hash' => null], ['id' => $row->id]);
        $local = Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local');
        $adapter = $this->createStub(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturnCallback(fn ($path) => $local->fileExists($path));
        $adapter->method('fileSize')->willReturnCallback(fn ($path) => $local->fileSize($path));
        $adapter->method('readStream')->willReturnCallback(function ($path) use ($row, $local) {
            $this->FileStorage->updateAll(['path' => 'changed.jpg'], ['id' => $row->id]);

            return $local->readStream($path);
        });
        $this->wrapAdapter($adapter);
        $report = $this->runService(['hashOnly' => true]);
        $this->assertSame(0, $report->hashed);
        $this->assertSame(1, $report->skipped[ExistingFileDeduplicator::SKIP_CHANGED]);
        $this->assertNull($this->FileStorage->get($row->id)->hash);
        $this->keepSource($row, file_get_contents($this->fileFixtures . 'titus.jpg'));
    }

    public function testInvalidRegisteredPathFailsOnlyThatRow(): void
    {
        $row = $this->legacy();
        $this->FileStorage->getConnection()->transactional(function () use ($row): void {
            $registry = new BlobRegistry($this->FileStorage);
            $claim = $registry->claim('Local', $row->hash, DateTime::now());
            $registry->recordPath($claim->id, 'elsewhere/file.jpg');
        });
        $report = $this->runService();
        $this->assertSame(1, $report->failures);
        $this->assertStringContainsString('Registered blob path is invalid', $report->failureSamples[0]);
        $this->assertSame($row->path, $this->FileStorage->get($row->id)->path);
        $this->keepSource($row, file_get_contents($this->fileFixtures . 'titus.jpg'));
    }

    #[DataProvider('databasePreconditionProvider')]
    public function testDatabasePreconditions(bool $unsupported): void
    {
        $original = $this->FileStorage->getConnection();
        $connection = $this->createStub(Connection::class);
        $connection->method('getDriver')->willReturn($this->createStub($unsupported ? Driver::class : Sqlite::class));
        if (!$unsupported) {
            $schema = $this->createStub(CollectionInterface::class);
            $schema->method('listTables')->willReturn([]);
            $connection->method('getSchemaCollection')->willReturn($schema);
        }
        $this->FileStorage->setConnection($connection);
        try {
            $this->runService();
            $this->fail('Expected database precondition failure');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($unsupported ? 'Unsupported' : 'file_storage_blobs', $exception->getMessage());
        } finally {
            $this->FileStorage->setConnection($original);
        }
    }

    public static function databasePreconditionProvider(): array
    {
        return [[true], [false]];
    }

    public function testMissingStoragePrecondition(): void
    {
        Configure::delete('FileStorage.behaviorConfig.fileStorage');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not configured');
        $this->runService();
    }
}
