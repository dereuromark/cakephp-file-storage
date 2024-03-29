<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;
use FileStorage\Filesystem\Folder;
use FileStorage\Model\Table\FileStorageTable;
use Intervention\Image\ImageManager;
use PhpCollective\Infrastructure\Storage\Factories\LocalFactory;
use PhpCollective\Infrastructure\Storage\FileStorage;
use PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageProcessor;
use PhpCollective\Infrastructure\Storage\Processor\StackProcessor;
use PhpCollective\Infrastructure\Storage\StorageAdapterFactory;
use PhpCollective\Infrastructure\Storage\StorageService;
use TestApp\Storage\Processor\ImageDimensionsProcessor;

/**
 * FileStorageTestCase
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 */
class FileStorageTestCase extends TestCase
{
    use LocatorAwareTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    protected array $fixtures = [
        'plugin.FileStorage.FileStorage',
    ];

    /**
     * FileStorage Table instance.
     *
     * @var \FileStorage\Model\Table\FileStorageTable
     */
    protected $FileStorage;

    /**
     * Path to the file fixtures, set in the setUp() method.
     *
     * @var string
     */
    protected string $fileFixtures;

    /**
     * Test file path
     *
     * @var string
     */
    protected string $testPath = '';

    /**
     * Setup test folders and files
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->testPath = TMP . 'file-storage-test' . DS;
        $this->fileFixtures = Plugin::path('FileStorage') . 'tests' . DS . 'Fixture' . DS . 'File' . DS;

        if (!is_dir($this->testPath)) {
            mkdir($this->testPath);
        }

        $this->prepareDependencies();
        $this->configureImageVariants();

        $this->FileStorage = $this
            ->getTableLocator()
            ->get(FileStorageTable::class);
    }

    /**
     * @return void
     */
    protected function configureImageVariants(): void
    {
        Configure::write('FileStorage.imageVariants', [
            'Photos' => [
                'Photos' => [
                    'thumbnail' => [
                        'width' => 50,
                        'height' => 50,
                    ],
                ],
                't150' => [
                    'thumbnail' => [
                        'width' => 150,
                        'height' => 150,
                    ],
                ],
            ],
            'Avatars' => [
                'Avatars' => [
                    'thumbnail' => [
                        'width' => 80,
                        'height' => 80,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    protected function prepareDependencies(): void
    {
        $pathBuilder = new PathBuilder([
            'pathTemplate' => '{model}{ds}{collection}{ds}{randomPath}{ds}{strippedId}{ds}{strippedId}.{extension}',
            'variantPathTemplate' => '{model}{ds}{collection}{ds}{randomPath}{ds}{strippedId}{ds}{strippedId}.{hashedVariant}.{extension}',
        ]);

        $storageService = new StorageService(
            new StorageAdapterFactory(),
        );

        $storageService->setAdapterConfigFromArray([
            'Local' => [
                'class' => LocalFactory::class,
                'options' => [
                    'root' => $this->testPath, true,
                ],
            ],
        ]);

        $fileStorage = new FileStorage(
            $storageService,
            $pathBuilder,
        );

        $imageManager = new ImageManager([
            'driver' => 'gd',
        ]);

        $imageProcessor = new ImageProcessor(
            $fileStorage,
            $pathBuilder,
            $imageManager,
        );
        $imageDimensionsProcessor = new ImageDimensionsProcessor(
            $this->testPath,
        );
        $stackProcessor = new StackProcessor([
            $imageProcessor,
            $imageDimensionsProcessor,
        ]);

        Configure::write('FileStorage.behaviorConfig', [
            'fileStorage' => $fileStorage,
            'fileProcessor' => $stackProcessor,
        ]);
    }

    /**
     * Cleanup test files
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->getTableLocator()->clear();
        $Folder = new Folder($this->testPath);
        $Folder->delete();
    }

    /**
     * Creates a file
     *
     * @param string $file File path and name, relative to FileStorageTestCase::$testPath
     *
     * @return string
     */
    protected function _createMockFile(string $file): string
    {
        if (DS === '/') {
            $file = str_replace('\\', DS, $file);
        } else {
            $file = str_replace('/', DS, $file);
        }

        $path = dirname($file);
        if (!is_dir($this->testPath . $path)) {
            mkdir($this->testPath . $path, 0777, true);
        }

        if (!file_exists($this->testPath . $file)) {
            touch($this->testPath . $file);
        }

        return $this->testPath . $file;
    }
}
