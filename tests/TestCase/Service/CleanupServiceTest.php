<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use FileStorage\Service\BlobRegistry;
use FileStorage\Service\CleanupService;
use FileStorage\Test\TestCase\FileStorageTestCase;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use PhpCollective\Infrastructure\Storage\FileStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class CleanupServiceTest extends FileStorageTestCase
{
    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    protected BlobRegistry $registry;

    public function setUp(): void
    {
        parent::setUp();
        $this->FileStorage->deleteAll([]);
        $this->registry = new BlobRegistry($this->FileStorage);
        Configure::write('FileStorage.pathPrefix', 'cleanup-service-unused/');
        Configure::write('FileStorage.deduplicate', ['collections' => false, 'gracePeriod' => 3600, 'root' => 'blobs']);
    }

    public function tearDown(): void
    {
        Configure::delete('FileStorage.deduplicate');
        Configure::delete('FileStorage.pathPrefix');
        parent::tearDown();
    }

    protected function blob(string $name, bool $young = false, bool $reference = false, string $adapter = 'Local'): string
    {
        $path = 'blobs/' . hash('sha256', $name) . '.txt';
        touch($this->_createMockFile($path), time() - 7200);
        $connection = $this->FileStorage->getConnection();
        $connection->begin();
        $claim = $this->registry->claim($adapter, hash('sha256', $name), DateTime::now()->subSeconds($young ? 0 : 7200));
        $this->registry->recordPath($claim->id, $path);
        $connection->commit();
        if ($reference) {
            $connection->execute(
                'INSERT INTO file_storage (uuid, filename, adapter, path, blob_id, foreign_key, model) VALUES (:uuid, :filename, :adapter, :path, :id, :owner, :model)',
                ['uuid' => $name, 'filename' => 'file.txt', 'adapter' => $adapter, 'path' => $path, 'id' => $claim->id, 'owner' => '1', 'model' => 'Items'],
                ['id' => 'integer'],
            );
        }

        return $path;
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function modes(): array
    {
        return ['off' => [false, false], 'on' => [true, false], 'preview off' => [false, true], 'preview on' => [true, true]];
    }

    #[DataProvider('modes')]
    public function testBlobAndStrayPasses(bool $enabled, bool $dryRun): void
    {
        Configure::write('FileStorage.deduplicate.collections', $enabled);
        $referenced = $this->blob('referenced', reference: true);
        $young = $this->blob('young', young: true);
        $old = $this->blob('old');
        $stray = 'blobs/' . hash('sha256', 'stray') . '.txt';
        touch($this->_createMockFile($stray), time() - 7200);
        $youngStray = 'blobs/' . hash('sha256', 'young stray') . '.txt';
        touch($this->_createMockFile($youngStray));
        $invalid = 'blobs/not-a-hash.txt';
        touch($this->_createMockFile($invalid), time() - 7200);

        $report = (new CleanupService())->run('OtherModel', 'OtherCollection', $dryRun);

        $this->assertSame([$old], $report->deletedBlobs);
        $this->assertSame([$stray], $report->deletedStrayBlobs);
        $this->assertSame(2, $report->skippedBlobs);
        $this->assertStringContainsString('Invalid hash', implode('\n', $report->warnings));
        foreach ([$referenced, $young, $youngStray, $invalid] as $path) {
            $this->assertFileExists($this->testPath . $path);
        }
        $this->assertSame($dryRun, is_file($this->testPath . $old));
        $this->assertSame($dryRun, is_file($this->testPath . $stray));
        $this->assertSame($dryRun ? 3 : 2, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
    }

    public function testDeleteFailureKeepsBlobRow(): void
    {
        $path = $this->blob('failed');
        $adapter = $this->createStub(FilesystemAdapter::class);
        $adapter->method('fileExists')->willReturn(true);
        $adapter->method('delete')->willThrowException(new RuntimeException('Delete failed'));
        $adapter->method('listContents')->willReturn([]);
        $storage = $this->createStub(FileStorage::class);
        $storage->method('getStorage')->willReturn($adapter);
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);

        $report = (new CleanupService())->run(null, null, false);

        $this->assertSame([], $report->deletedBlobs);
        $this->assertContains('Delete failed', $report->warnings);
        $this->assertSame(1, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
        $this->assertFileExists($this->testPath . $path);
    }

    public function testMissingBlobFileIsTolerated(): void
    {
        $path = $this->blob('missing');
        unlink($this->testPath . $path);
        $report = (new CleanupService())->run(null, null, false);
        $this->assertSame([$path], $report->deletedBlobs);
        $this->assertSame(0, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
    }

    public function testLastBlobAdapterStillScannedAfterSweep(): void
    {
        $this->blob('last');
        $stray = 'blobs/' . hash('sha256', 'remaining') . '.txt';
        touch($this->_createMockFile($stray), time() - 7200);
        $report = (new CleanupService())->run(null, null, false);
        $this->assertSame([$stray], $report->deletedStrayBlobs);
        $this->assertFileDoesNotExist($this->testPath . $stray);
    }

    public function testUnknownModificationTimeIsSkipped(): void
    {
        $this->blob('registered', young: true);
        $adapter = $this->createStub(FilesystemAdapter::class);
        $adapter->method('listContents')->willReturn([new FileAttributes('blobs/' . hash('sha256', 'unknown') . '.txt')]);
        $storage = $this->createStub(FileStorage::class);
        $storage->method('getStorage')->willReturn($adapter);
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);
        $report = (new CleanupService())->run(null, null, false);
        $this->assertSame([], $report->deletedStrayBlobs);
        $this->assertSame(1, $report->skippedBlobs);
        $this->assertStringContainsString('Unknown modification time', implode(' ', $report->warnings));
    }

    public function testListingFailureDoesNotStopOtherAdapters(): void
    {
        $unavailable = $this->blob('unavailable', young: true, adapter: 'Broken');
        unlink($this->testPath . $unavailable);
        $this->blob('available', young: true);
        $stray = 'blobs/' . hash('sha256', 'stray') . '.txt';
        touch($this->_createMockFile($stray), time() - 7200);
        $local = Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Local');
        $broken = $this->createStub(FilesystemAdapter::class);
        $broken->method('listContents')->willThrowException(new RuntimeException('Listing failed'));
        $storage = $this->createStub(FileStorage::class);
        $storage->method('getStorage')->willReturnMap([['Broken', $broken], ['Local', $local]]);
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);
        $report = (new CleanupService())->run(null, null, false);
        $this->assertSame([$stray], $report->deletedStrayBlobs);
        $this->assertStringContainsString('Listing failed', implode(' ', $report->warnings));
    }

    public function testMissingRootIsNotAnError(): void
    {
        $path = $this->blob('missing root', young: true);
        unlink($this->testPath . $path);
        rmdir($this->testPath . 'blobs');
        $report = (new CleanupService())->run('OtherModel', null, false);
        $this->assertSame([], $report->deletedStrayBlobs);
        $this->assertStringNotContainsString('Could not list blobs', implode(' ', $report->warnings));
    }

    public function testUnresolvableAdapterWarns(): void
    {
        $this->blob('unknown adapter', young: true, adapter: 'Unknown');
        $report = (new CleanupService())->run(null, null, false);
        $this->assertStringContainsString('Could not list blobs on Unknown', implode(' ', $report->warnings));
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function scopes(): array
    {
        return ['all' => [null, 'blobs'], 'model' => ['Items', 'Items/blobs']];
    }

    #[DataProvider('scopes')]
    public function testOrphanFilesProtectBlobRoot(?string $model, string $root): void
    {
        $originalPath = $this->testPath;
        $this->testPath = WWW_ROOT . 'cleanup-test/';
        mkdir($this->testPath, 0777, true);
        Configure::write('FileStorage.pathPrefix', 'cleanup-test/');
        Configure::write('FileStorage.deduplicate.root', $root);
        $blob = $this->_createMockFile($root . '/unsafe-name.txt');
        $orphan = $this->_createMockFile(($model === null ? '' : $model . '/') . 'orphan.txt');
        try {
            $report = (new CleanupService())->run($model, null, false);
            $this->assertFileExists($blob);
            $this->assertFileDoesNotExist($orphan);
            $this->assertSame([$orphan], $report->deletedFiles);
        } finally {
            $this->removeDirectoryRecursive($this->testPath);
            $this->testPath = $originalPath;
        }
    }

    /**
     * @return void
     */
    public function testRunBlobsLeavesOrphanRowsAlone(): void
    {
        $path = $this->blob('blobs only');
        $this->FileStorage->getConnection()->execute(
            'INSERT INTO file_storage (uuid, filename, adapter, path, model) VALUES (:uuid, :filename, :adapter, :path, :model)',
            ['uuid' => 'standalone', 'filename' => 'file.txt', 'adapter' => 'Local', 'path' => 'Items/file.txt', 'model' => 'Items'],
        );
        $orphans = $this->FileStorage->find()->where(['foreign_key IS' => null])->count();
        $this->assertSame(1, $orphans);

        $report = (new CleanupService())->runBlobs(false);

        $this->assertSame([$path], $report->deletedBlobs);
        $this->assertSame(0, $report->deletedRows);
        $this->assertSame($orphans, $this->FileStorage->find()->where(['foreign_key IS' => null])->count());
    }
}
