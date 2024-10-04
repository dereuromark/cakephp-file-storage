<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * @uses \FileStorage\Command\CleanupCommand
 */
class ImageVariantGenerateCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @return void
     */
    public function testRun(): void
    {
        $this->exec('file_storage generate_image_variant');

        $this->assertExitCode(0);
        $this->assertOutputContains('No images found');
    }
}
