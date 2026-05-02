<?php declare(strict_types=1);

namespace FileStorage\Utility;

use InvalidArgumentException;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

class StorageUtils
{
    /**
     * @param string $filename
     * @param string|null $mimeType
     *
     * @return \Psr\Http\Message\UploadedFileInterface
     */
    public static function fileToUploadedFileObject(string $filename, ?string $mimeType = null): UploadedFileInterface
    {
        $size = static::assertReadableFile($filename);

        return new UploadedFile(
            $filename,
            $size,
            UPLOAD_ERR_OK,
            basename($filename),
            $mimeType,
        );
    }

    /**
     * @param string $filename
     * @param string|null $mimeType
     *
     * @return array
     */
    public static function fileToUploadedFileArray(string $filename, ?string $mimeType = null): array
    {
        $size = static::assertReadableFile($filename);

        return [
            'tmp_name' => $filename,
            'size' => $size,
            'error' => UPLOAD_ERR_OK,
            'name' => basename($filename),
            'type' => $mimeType,
        ];
    }

    /**
     * @param string $filename
     *
     * @throws \InvalidArgumentException When $filename does not point to a readable regular file.
     *
     * @return int Verified file size in bytes.
     */
    protected static function assertReadableFile(string $filename): int
    {
        clearstatcache(true, $filename);
        if (!is_file($filename) || !is_readable($filename)) {
            throw new InvalidArgumentException(sprintf('File `%s` is not a readable file.', $filename));
        }

        $size = filesize($filename);
        if ($size === false) {
            throw new InvalidArgumentException(sprintf('Cannot determine size of file `%s`.', $filename));
        }

        return $size;
    }
}
