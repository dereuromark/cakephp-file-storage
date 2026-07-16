<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use FileStorage\Test\TestCase\FileStorageTestCase;
use PhpCollective\Infrastructure\Storage\Factories\LocalFactory;
use PhpCollective\Infrastructure\Storage\FileStorage;
use PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder;
use PhpCollective\Infrastructure\Storage\StorageAdapterFactory;
use PhpCollective\Infrastructure\Storage\StorageService;

/**
 * @uses \FileStorage\Command\MigrateAdapterCommand
 */
class MigrateAdapterCommandTest extends FileStorageTestCase
{
    use ConsoleIntegrationTestTrait;

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
}
