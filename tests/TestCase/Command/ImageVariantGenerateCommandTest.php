<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use FileStorage\Command\ImageVariantGenerateCommand;
use PhpCollective\Infrastructure\Storage\File;
use ReflectionClass;

/**
 * @uses \FileStorage\Command\ImageVariantGenerateCommand
 */
class ImageVariantGenerateCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.FileStorage.FileStorage',
    ];

    /**
     * @return void
     */
    public function testRun(): void
    {
        $this->exec('file_storage generate_image_variant');

        $this->assertExitCode(0);
        $this->assertOutputContains('No images found');
    }

    /**
     * @return void
     */
    public function testProcessImages(): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $command = new ImageVariantGenerateCommand();

        $entity = $table->get(1);
        $file = File::create(
            'test.jpg',
            12345,
            'image/jpeg',
            'Local',
            'Photos',
            'Item',
            '1',
            [],
            [],
        );

        $operations = [
            'thumb' => ['width' => 100, 'height' => 100],
            'medium' => ['width' => 300, 'height' => 300],
        ];

        $result = $command->processImages($file, $entity, $operations, false);

        $this->assertInstanceOf(File::class, $result);
    }

    /**
     * @return void
     */
    public function testProcessImagesWithEmptyOperations(): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $command = new ImageVariantGenerateCommand();

        $entity = $table->get(1);
        $file = File::create(
            'test.jpg',
            12345,
            'image/jpeg',
            'Local',
            'Photos',
            'Item',
            '1',
            [],
            [],
        );

        $result = $command->processImages($file, $entity, [], false);

        $this->assertSame($file, $result);
    }

    /**
     * @return void
     */
    public function testEntityToFileObject(): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $command = new ImageVariantGenerateCommand();

        // Set the table on the command via reflection since it's protected
        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('Table');
        $property->setValue($command, $table);

        $entity = $table->get(1);
        $file = $command->entityToFileObject($entity);

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('cake.icon.png', $file->filename());
    }

    /**
     * @return void
     */
    public function testFileObjectToEntity(): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $command = new ImageVariantGenerateCommand();

        // Set the table on the command via reflection since it's protected
        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('Table');
        $property->setValue($command, $table);

        $file = File::create(
            'test.jpg',
            54321,
            'image/jpeg',
            'Local',
            'Photos',
            'Item',
            '1',
            ['width' => 800],
            ['thumb' => ['path' => 'thumb.jpg']],
        );
        $file = $file->withUuid('new-test-id')
            ->withPath('Item/test.jpg');

        $entity = $command->fileObjectToEntity($file, null);

        $this->assertSame('test.jpg', $entity->filename);
        $this->assertSame(54321, $entity->filesize);
        $this->assertSame('image/jpeg', $entity->mime_type);
    }
}
