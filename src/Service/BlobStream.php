<?php declare(strict_types=1);

namespace FileStorage\Service;

use League\Flysystem\FilesystemAdapter;
use RuntimeException;

/**
 * Hashes stored content with bounded memory use.
 *
 * @author Mark Scherer
 * @license MIT
 */
final class BlobStream
{
    public static function hash(FilesystemAdapter $adapter, string $path): string
    {
        $stream = $adapter->readStream($path);
        if (!is_resource($stream)) {
            throw new RuntimeException('Could not open blob stream.');
        }
        try {
            $context = hash_init('sha256');
            while (!feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false || ($chunk === '' && !feof($stream))) {
                    throw new RuntimeException('Blob read failed.');
                }
                hash_update($context, $chunk);
            }

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
