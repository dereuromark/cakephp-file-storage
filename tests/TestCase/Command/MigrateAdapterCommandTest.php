<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use FileStorage\Service\AdapterMigrationService;
use FileStorage\Service\BlobRegistry;
use FileStorage\Test\TestCase\FileStorageTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use PhpCollective\Infrastructure\Storage\Factories\LocalFactory;
use PhpCollective\Infrastructure\Storage\FileStorage;
use PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder;
use PhpCollective\Infrastructure\Storage\StorageAdapterFactory;
use PhpCollective\Infrastructure\Storage\StorageService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * @uses \FileStorage\Command\MigrateAdapterCommand
 */
class MigrateAdapterCommandTest extends FileStorageTestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    protected string $targetPath = '';

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->targetPath = TMP . 'file-storage-target-test' . DS;
        if (!is_dir($this->targetPath)) {
            mkdir($this->targetPath);
        }

        $this->configureMigrationAdapters();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->removeDirectoryRecursive($this->targetPath);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testDryRun(): void
    {
        $this->_createMockFile('Item/cake.icon.png');

        $this->exec('file_storage migrate_adapter Local Target --dryRun --limit=1');

        $this->assertExitCode(0);
        $this->assertOutputContains('Checked 1 row(s).');
        $this->assertOutputContains('1 row(s) would be migrated, 1 file(s) would be copied.');
        $this->assertFileDoesNotExist($this->targetPath . 'Item/cake.icon.png');
        $this->assertSame('Local', $this->FileStorage->get(1)->adapter);
    }

    /**
     * @return void
     */
    public function testRunCopiesFilesAndUpdatesRows(): void
    {
        $source = $this->_createMockFile('Item/cake.icon.png');
        file_put_contents($source, 'file contents');

        $this->exec('file_storage migrate_adapter Local Target --limit=1');

        $this->assertExitCode(0);
        $this->assertOutputContains('Checked 1 row(s).');
        $this->assertOutputContains('1 row(s) migrated, 1 file(s) copied.');
        $this->assertFileExists($this->targetPath . 'Item/cake.icon.png');
        $this->assertSame('file contents', file_get_contents($this->targetPath . 'Item/cake.icon.png'));
        $this->assertSame('Target', $this->FileStorage->get(1)->adapter);
    }

    protected function prepareBlobRows(): string
    {
        $this->FileStorage->deleteAll([]);
        $hash = hash('sha256', 'shared contents');
        $path = 'blobs/' . $hash . '.txt';
        file_put_contents($this->_createMockFile($path), 'shared contents');
        $connection = $this->FileStorage->getConnection();
        $connection->begin();
        $registry = new BlobRegistry($this->FileStorage);
        $claim = $registry->claim('Local', $hash, DateTime::now());
        $registry->recordPath($claim->id, $path);
        $connection->commit();
        foreach ([1, 2] as $id) {
            $connection->execute(
                'INSERT INTO file_storage (id, uuid, filename, adapter, path, hash, blob_id, foreign_key) VALUES (:id, :uuid, :filename, :adapter, :path, :hash, :blob, :owner)',
                ['id' => $id, 'uuid' => 'row-' . $id, 'filename' => 'file.txt', 'adapter' => 'Local', 'path' => $path, 'hash' => $hash, 'blob' => $claim->id, 'owner' => '1'],
                ['id' => 'integer', 'blob' => 'integer'],
            );
        }

        return $path;
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function targetStates(): array
    {
        return ['new target' => [false], 'existing target' => [true]];
    }

    #[DataProvider('targetStates')]
    public function testSharedBlobsMigrate(bool $existing): void
    {
        $path = $this->prepareBlobRows();
        $targetPath = $existing ? 'blobs/existing.txt' : $path;
        if ($existing) {
            mkdir($this->targetPath . 'blobs');
            file_put_contents($this->targetPath . $targetPath, 'shared contents');
            $connection = $this->FileStorage->getConnection();
            $connection->begin();
            $registry = new BlobRegistry($this->FileStorage);
            $claim = $registry->claim('Target', hash('sha256', 'shared contents'), DateTime::now());
            $registry->recordPath($claim->id, $targetPath);
            $connection->commit();
        }
        $this->exec('file_storage migrate_adapter Local Target');
        $this->assertExitCode(0);
        $this->assertOutputContains($existing ? '2 row(s) migrated, 0 file(s) copied.' : '2 row(s) migrated, 1 file(s) copied.');
        $first = $this->FileStorage->get(1);
        $second = $this->FileStorage->get(2);
        $this->assertSame('Target', $first->adapter);
        $this->assertSame('Target', $second->adapter);
        $this->assertSame($targetPath, $first->path);
        $this->assertSame($targetPath, $second->path);
        $this->assertSame($first->blob_id, $second->blob_id);
        $this->assertSame(1, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->where(['adapter' => 'Target'])->count());
        $files = iterator_to_array(Configure::read('FileStorage.behaviorConfig.fileStorage')->getStorage('Target')->listContents('blobs', true));
        $this->assertCount(1, array_filter($files, static fn ($file): bool => $file->isFile()));
        $this->assertSame('shared contents', file_get_contents($this->targetPath . $targetPath));
    }

    public function testDeleteSourceLeavesSharedBlob(): void
    {
        $path = $this->prepareBlobRows();
        $variant = 'variants/one.txt';
        $this->_createMockFile($variant);
        $this->FileStorage->updateAll(['variants' => ['thumbnail' => ['path' => $variant]]], ['id' => 1]);
        $this->exec('file_storage migrate_adapter Local Target --deleteSource');
        $this->assertExitCode(0);
        $this->assertErrorContains('1 source file(s) deleted.');
        $this->assertFileDoesNotExist($this->testPath . $variant);
        $this->assertFileExists($this->targetPath . $variant);
        $this->assertFileExists($this->testPath . $path);
        $this->assertSame(1, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->where(['adapter' => 'Local'])->count());
    }

    public function testBlobDryRunMakesNoClaims(): void
    {
        $path = $this->prepareBlobRows();
        $before = $this->FileStorage->find()->all()->toArray();
        $this->exec('file_storage migrate_adapter Local Target --dryRun --deleteSource');
        $this->assertExitCode(0);
        $this->assertFileDoesNotExist($this->targetPath . $path);
        $this->assertFileExists($this->testPath . $path);
        $this->assertEquals($before, $this->FileStorage->find()->all()->toArray());
        $this->assertSame(1, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
    }

    public function testVariantTargetConflictRollsBackClaim(): void
    {
        $this->prepareBlobRows();
        $variant = 'variants/existing.txt';
        $this->_createMockFile($variant);
        mkdir($this->targetPath . 'variants');
        file_put_contents($this->targetPath . $variant, 'existing');
        $this->FileStorage->updateAll(['variants' => ['thumbnail' => ['path' => $variant]]], ['id' => 1]);
        $report = (new AdapterMigrationService())->run('Local', 'Target', ['limit' => 1]);
        $this->assertSame(0, $report->migratedRows);
        $this->assertCount(1, $report->skippedRows);
        $this->assertSame('Local', $this->FileStorage->get(1)->adapter);
        $this->assertSame(1, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
        $this->assertSame('existing', file_get_contents($this->targetPath . $variant));
    }

    public function testCopyFailureRollsBackRowAndContinues(): void
    {
        $this->prepareBlobRows();
        $configured = Configure::read('FileStorage.behaviorConfig.fileStorage');
        $source = $configured->getStorage('Local');
        $localTarget = $configured->getStorage('Target');
        $calls = 0;
        $target = $this->createStub(FilesystemAdapter::class);
        $target->method('writeStream')->willReturnCallback(function (string $path, $stream, Config $config) use (&$calls, $localTarget): void {
            if ($calls++ === 0) {
                throw new RuntimeException('Copy failed');
            }
            $localTarget->writeStream($path, $stream, $config);
        });
        $storage = $this->createStub(FileStorage::class);
        $storage->method('getStorage')->willReturnMap([['Local', $source], ['Target', $target]]);
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);
        $report = (new AdapterMigrationService())->run('Local', 'Target');
        $this->assertSame(1, $report->migratedRows);
        $this->assertSame(1, $report->copiedFiles);
        $this->assertCount(1, $report->failures);
        $this->assertStringContainsString('Copy failed', $report->failures[0]);
        $this->assertSame('Local', $this->FileStorage->get(1)->adapter);
        $this->assertSame('Target', $this->FileStorage->get(2)->adapter);
        $this->assertSame(2, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
        $this->assertFalse($this->FileStorage->getConnection()->inTransaction());
    }

    /**
     * @return void
     */
    public function testFailedAttemptRemovesTheFilesItCreated(): void
    {
        $this->prepareBlobRows();
        $variant = 'variants/one.txt';
        $this->_createMockFile($variant);
        $this->FileStorage->updateAll(['variants' => ['thumbnail' => ['path' => $variant]]], ['id' => 1]);
        $configured = Configure::read('FileStorage.behaviorConfig.fileStorage');
        $source = $configured->getStorage('Local');
        $localTarget = $configured->getStorage('Target');
        $calls = 0;
        $target = $this->createStub(FilesystemAdapter::class);
        $target->method('fileExists')->willReturnCallback(fn (string $path): bool => $localTarget->fileExists($path));
        $target->method('delete')->willReturnCallback(function (string $path) use ($localTarget): void {
            $localTarget->delete($path);
        });
        $target->method('writeStream')->willReturnCallback(function (string $path, $stream, Config $config) use (&$calls, $localTarget): void {
            // The variant is copied first, the blob second.
            if ($calls++ === 1) {
                throw new RuntimeException('Copy failed');
            }
            $localTarget->writeStream($path, $stream, $config);
        });
        $storage = $this->createStub(FileStorage::class);
        $storage->method('getStorage')->willReturnMap([['Local', $source], ['Target', $target]]);
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);

        $report = (new AdapterMigrationService())->run('Local', 'Target', ['limit' => 1]);

        $this->assertSame(0, $report->migratedRows);
        $this->assertCount(1, $report->failures);
        $this->assertSame('Local', $this->FileStorage->get(1)->adapter);
        $this->assertFileDoesNotExist($this->targetPath . $variant);
        $this->assertFileExists($this->testPath . $variant);
    }

    public function testChangedSourceRowIsSkipped(): void
    {
        $this->prepareBlobRows();
        $service = new class extends AdapterMigrationService {
            protected function streamRows(string $sourceAdapter, array $options): iterable
            {
                foreach (parent::streamRows($sourceAdapter, $options) as $entity) {
                    $this->fetchTable('FileStorage.FileStorage')->updateAll(['blob_id' => null], ['id' => $entity->id]);

                    yield $entity;
                }
            }
        };
        $report = $service->run('Local', 'Target');
        $this->assertSame(0, $report->migratedRows);
        $this->assertCount(2, $report->skippedRows);
        $this->assertStringContainsString('source adapter or blob reference changed', $report->skippedRows[0]);
        $this->assertSame(1, $this->fetchTable('FileStorage.FileStorageBlobs')->find()->count());
    }

    /**
     * @return void
     */
    protected function configureMigrationAdapters(): void
    {
        $storageService = new StorageService(
            new StorageAdapterFactory(),
        );
        $storageService->setAdapterConfigFromArray([
            'Local' => [
                'class' => LocalFactory::class,
                'options' => [
                    'root' => $this->testPath,
                    true,
                ],
            ],
            'Target' => [
                'class' => LocalFactory::class,
                'options' => [
                    'root' => $this->targetPath,
                    true,
                ],
            ],
        ]);

        Configure::write('FileStorage.behaviorConfig.fileStorage', new FileStorage(
            $storageService,
            new PathBuilder(),
        ));
    }

    /**
     * @return void
     */
    public function testMissingTargetBlobFileIsRestoredFromSource(): void
    {
        $path = $this->prepareBlobRows();
        $targetPath = 'blobs/registered-but-gone.txt';
        $connection = $this->FileStorage->getConnection();
        $connection->begin();
        $registry = new BlobRegistry($this->FileStorage);
        $claim = $registry->claim('Target', hash('sha256', 'shared contents'), DateTime::now());
        $registry->recordPath($claim->id, $targetPath);
        $connection->commit();

        $report = (new AdapterMigrationService())->run('Local', 'Target', ['limit' => 1]);

        $this->assertSame(1, $report->migratedRows);
        $this->assertSame($targetPath, $this->FileStorage->get(1)->path);
        $this->assertSame('shared contents', file_get_contents($this->targetPath . $targetPath));
        $this->assertFileDoesNotExist($this->targetPath . $path);
    }
}
