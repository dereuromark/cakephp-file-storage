<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Service;

use Cake\Core\Configure;
use Closure;
use FileStorage\Model\Entity\FileStorage;
use FileStorage\Service\AdapterMigrationService;
use FileStorage\Service\ExistingFileDeduplicator;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PhpCollective\Infrastructure\Storage\FileStorage as Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Checks migration revalidation of legacy rows before copying.
 *
 * @author Mark Scherer
 * @license MIT
 */
class AdapterMigrationServiceTest extends FileStorageTestCase
{
    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    public function setUp(): void
    {
        parent::setUp();
        $this->FileStorage = $this->fetchTable('FileStorage.FileStorage');
        $this->FileStorage->deleteAll([]);
        Configure::write('FileStorage.deduplicate.collections', false);
        $this->FileStorage->removeBehavior('FileStorage');
        $this->FileStorage->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
    }

    public function tearDown(): void
    {
        Configure::delete('FileStorage.deduplicate');
        parent::tearDown();
    }

    #[DataProvider('changeProvider')]
    public function testChangedLegacyRowIsSkipped(string $change): void
    {
        $row = $this->FileStorage->newEntity([
            'model' => 'Items',
            'collection' => 'Photos',
            'file' => new UploadedFile($this->fileFixtures . 'titus.jpg', filesize($this->fileFixtures . 'titus.jpg'), UPLOAD_ERR_OK, 'titus.jpg', 'image/jpeg'),
        ]);
        $this->FileStorage->saveOrFail($row);
        Configure::write('FileStorage.deduplicate.collections', true);
        $original = Configure::read('FileStorage.behaviorConfig.fileStorage');
        $target = new LocalFilesystemAdapter($this->testPath . 'target');
        $storage = $this->createStub(Storage::class);
        $storage->method('getStorage')->willReturnCallback(fn ($name) => $name === 'Target' ? $target : $original->getStorage($name));
        $storage->method('buildPath')->willReturnCallback(fn ($file) => $original->buildPath($file));
        Configure::write('FileStorage.behaviorConfig.fileStorage', $storage);
        $mutation = function () use ($row, $change): void {
            if ($change === 'convert') {
                $report = (new ExistingFileDeduplicator())->run();
                $this->assertSame(1, $report->converted);
            } elseif ($change === 'delete') {
                $this->FileStorage->deleteAll(['id' => $row->id]);
            } else {
                $this->FileStorage->updateAll([$change => 'changed'], ['id' => $row->id]);
            }
        };
        $service = new class ($row, $mutation) extends AdapterMigrationService {
            public function __construct(protected FileStorage $listed, protected Closure $mutation)
            {
            }

            protected function streamRows(string $sourceAdapter, array $options): iterable
            {
                ($this->mutation)();

                yield $this->listed;
            }
        };
        $report = $service->run('Local', 'Target', ['deleteSource' => true]);
        $this->assertSame(0, $report->migratedRows);
        $this->assertSame(0, $report->copiedFiles);
        $this->assertSame(0, $report->deletedSourceFiles);
        $this->assertCount(1, $report->skippedRows);
        $this->assertSame([], $report->failures);
        $this->assertSame([], iterator_to_array($target->listContents('', true)));
        $this->assertFileExists($this->testPath . $row->path);
        if ($change === 'convert') {
            $this->assertSame('Local', $this->FileStorage->get($row->id)->adapter);
            $this->assertNotSame(null, $this->FileStorage->get($row->id)->blob_id);
        }
    }

    public static function changeProvider(): array
    {
        return [['convert'], ['path'], ['adapter'], ['delete']];
    }
}
