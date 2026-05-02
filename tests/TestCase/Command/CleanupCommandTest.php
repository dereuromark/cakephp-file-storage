<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * @uses \FileStorage\Command\CleanupCommand
 */
class CleanupCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @return void
     */
    public function testRun(): void
    {
        $this->exec('file_storage cleanup -d');

        $this->assertExitCode(0);
        $this->assertOutputContains('Checking 0 file storage rows');
        $this->assertOutputContains('0 orphan row(s) would be deleted.');
    }
}
