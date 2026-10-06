<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use FileStorage\Command\ImageVariantGenerateCommand;
use FileStorage\Test\TestCase\FileStorageTestCase;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * @uses \FileStorage\Command\ImageVariantGenerateCommand
 */
class ImageVariantGenerateCommandTest extends FileStorageTestCase
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
     * @param bool $replace
     * @param bool $fail
     *
     * @return void
     */
    #[DataProvider('regenerationProvider')]
    public function testRegenerationPreservesReplacement(bool $replace, bool $fail): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $table->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
        $entity = $table->get(1);
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects($this->once())->method('process')->willReturnCallback(function ($file) use ($table, $replace, $fail) {
            if ($fail) {
                $table->updateAll(['filename' => 'rolled-back.png'], ['id' => 1]);

                throw new RuntimeException('Processor failed');
            }
            if (!$replace) {
                return $file;
            }
            $table->updateAll(['path' => 'replacement.png', 'filename' => 'replacement.png', 'filesize' => 42], ['id' => 1]);

            return $file->withPath('processed.png')->withFilename('processed.png');
        });
        try {
            $command = new class extends ImageVariantGenerateCommand {
                /**
                 * @param \Cake\ORM\Table $table
                 * @param \FileStorage\Model\Entity\FileStorage $entity
                 * @param \PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface $processor
                 *
                 * @return void
                 */
                public function regenerate($table, $entity, $processor): void
                {
                    $this->Table = $table;
                    $this->processor = $processor;
                    $this->_processEntity($entity, ['thumbnail' => ['width' => 50]]);
                }
            };
            $command->regenerate($table, $entity, $processor);
            $this->assertFalse($fail);
        } catch (RuntimeException $exception) {
            $this->assertTrue($fail);
            $this->assertSame('Processor failed', $exception->getMessage());
        }
        $fresh = $table->get(1);
        $this->assertSame($replace ? 'replacement.png' : $entity->path, $fresh->path);
        $this->assertSame($replace ? 'replacement.png' : $entity->filename, $fresh->filename);
        $this->assertSame($replace ? 42 : $entity->filesize, $fresh->filesize);
        if (!$replace && !$fail) {
            $this->assertSame(['thumbnail' => ['width' => 50]], $fresh->variants);
            $this->assertSame($fresh->variants, $entity->variants);
            $this->assertFalse($entity->isDirty('variants'));
        }
        if ($replace || $fail) {
            $this->assertSame([], $fresh->variants);
        }
        if ($replace) {
            $this->assertSame([], $entity->variants);
        }
        $this->assertTrue($table->hasBehavior('FileStorage'));
    }

    /**
     * @return array<string, array<bool>>
     */
    public static function regenerationProvider(): array
    {
        return [
            'replacement' => [true, false],
            'variants' => [false, false],
            'exception' => [false, true],
        ];
    }
}
