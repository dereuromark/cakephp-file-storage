<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use FileStorage\Exception\UploadException;
use FileStorage\Service\ResumableUploads;
use Laminas\Diactoros\Stream;

/**
 * Shared local upload test setup.
 *
 * @author Mark Scherer
 * @license MIT
 */
abstract class ResumableUploadTestCase extends FileStorageTestCase
{
    protected array $fixtures = ['plugin.FileStorage.FileStorageBlobs', 'plugin.FileStorage.FileStorage'];

    protected ResumableUploads $uploads;

    protected string $uploadPath;

    protected DateTime $now;

    public function setUp(): void
    {
        parent::setUp();
        $this->uploadPath = TMP . 'resumable-' . bin2hex(random_bytes(8));
        $this->now = DateTime::now();
        Configure::write('FileStorage.resumable', [
            'path' => $this->uploadPath,
            'minFreeBytes' => 0,
            'authorizer' => static fn (string $action, array $upload, array $context): array => ['userId' => $context['userId'] ?? '42'],
        ]);
        Configure::write('FileStorage.imageVariants', []);
        $this->FileStorage->getConnection()->execute('DELETE FROM file_storage_uploads');
        $this->uploads = new ResumableUploads($this->FileStorage, clock: fn (): DateTime => $this->now);
    }

    public function tearDown(): void
    {
        $this->FileStorage->getConnection()->execute('DELETE FROM file_storage_uploads');
        $this->removeDirectoryRecursive($this->uploadPath);
        Configure::delete('FileStorage.resumable');
        Configure::delete('FileStorage.deduplicate');
        parent::tearDown();
    }

    protected function metadata(array $extra = []): string
    {
        $pairs = [];
        foreach ($extra + ['model' => 'Items', 'collection' => 'Files', 'filename' => 'upload.bin'] as $key => $value) {
            $pairs[] = $key . ' ' . base64_encode($value);
        }

        return implode(',', $pairs);
    }

    protected function body(string $bytes): Stream
    {
        $stream = new Stream('php://temp', 'w+b');
        $stream->write($bytes);
        $stream->rewind();

        return $stream;
    }

    protected function part(array $row): string
    {
        return $this->uploadPath . '/' . $row['id'] . '.part';
    }

    protected function uploadStatus(int $expected, callable $call): UploadException
    {
        try {
            $call();
            $this->fail('Expected upload exception');
        } catch (UploadException $error) {
            $this->assertSame($expected, $error->status);

            return $error;
        }
    }
}
