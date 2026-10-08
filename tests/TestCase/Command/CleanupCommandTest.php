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
        $this->assertOutputContains('0 blob(s) would be deleted.');
        $this->assertOutputContains('0 stray blob file(s) would be deleted.');
        $this->assertOutputContains('0 blob(s) skipped.');
        $this->assertOutputContains('0 upload(s), 0 part file(s) would be deleted; 0 skipped.');
    }

    /**
     * @return void
     */
    public function testRunBlobsOnly(): void
    {
        $this->exec('file_storage cleanup -d --blobsOnly');

        $this->assertExitCode(0);
        $this->assertOutputNotContains('orphan row(s)');
        $this->assertOutputContains('0 blob(s) would be deleted.');
        $this->assertOutputContains('0 stray blob file(s) would be deleted.');
        $this->assertOutputNotContains('upload(s)');
    }

    /**
     * @return void
     */
    public function testRunUploadsOnly(): void
    {
        $this->exec('file_storage cleanup -d --uploadsOnly');

        $this->assertExitCode(0);
        $this->assertOutputNotContains('orphan row(s)');
        $this->assertOutputNotContains('blob(s)');
        $this->assertOutputContains('0 upload(s), 0 part file(s) would be deleted; 0 skipped.');
    }
}
