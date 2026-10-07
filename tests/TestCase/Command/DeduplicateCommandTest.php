<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use FileStorage\Test\TestCase\FileStorageTestCase;

/**
 * Checks command registration, options, and results.
 *
 * @author Mark Scherer
 * @license MIT
 */
class DeduplicateCommandTest extends FileStorageTestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    public function testPreview(): void
    {
        $this->exec('file_storage deduplicate Item Photos -d --limit 10');
        $this->assertExitCode(0);
        $this->assertOutputContains('0 candidate row(s), 0 bytes, 0 without hashes.');
        $this->assertOutputContains('0 estimated duplicate(s).');
    }

    public function testHashOnly(): void
    {
        $this->exec('file_storage deduplicate --hashOnly --manifest ' . $this->testPath . 'manifest.tsv');
        $this->assertExitCode(0);
        $this->assertOutputContains('0 row(s) hashed, 0 converted');
        $this->assertOutputContains('0 old file(s) kept, 0 bytes.');
    }

    public function testPreconditionFailure(): void
    {
        Configure::delete('FileStorage.behaviorConfig.fileStorage');
        $this->exec('file_storage deduplicate');
        $this->assertExitCode(1);
        $this->assertErrorContains('not configured');
    }

    public function testInvalidLimit(): void
    {
        $this->exec('file_storage deduplicate --limit 0');
        $this->assertExitCode(1);
        $this->assertErrorContains('positive integer');
    }

    public function testManifestFailure(): void
    {
        $this->exec('file_storage deduplicate --manifest ' . $this->testPath . 'missing/manifest.tsv');
        $this->assertExitCode(1);
        $this->assertErrorContains('Could not open manifest');
    }

    public function testHelp(): void
    {
        $this->exec('file_storage deduplicate --help');
        $this->assertExitCode(0);
        $this->assertOutputContains('Old files are kept');
        $this->assertOutputContains('Signed URLs');
        $this->assertOutputContains('--hashOnly');
        $this->assertOutputContains('--manifest');
        $this->assertOutputNotContains('--deleteOld');
    }
}
