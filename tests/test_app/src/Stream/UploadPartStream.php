<?php declare(strict_types=1);

namespace TestApp\Stream;

// PHP stream wrappers require these method names.
// phpcs:disable PSR1.Methods.CamelCapsMethodName

/**
 * Native part file with short writes and injected write failures.
 *
 * @author Mark Scherer
 * @license MIT
 */
class UploadPartStream
{
    public mixed $context;

    public static string $path;

    public static bool $failWrite = false;

    public static mixed $native;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$native = fopen(self::$path, 'r+b');

        return is_resource(self::$native);
    }

    public function stream_write(string $bytes): int|false
    {
        return self::$failWrite ? false : fwrite(self::$native, substr($bytes, 0, 2));
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return fseek(self::$native, $offset, $whence) === 0;
    }

    public function stream_tell(): int|false
    {
        return ftell(self::$native);
    }

    public function stream_stat(): array|false
    {
        return fstat(self::$native);
    }

    public function stream_lock(int $operation): bool
    {
        return flock(self::$native, $operation);
    }

    public function stream_flush(): bool
    {
        return fflush(self::$native);
    }

    public function stream_truncate(int $size): bool
    {
        return ftruncate(self::$native, $size);
    }

    public function stream_close(): void
    {
        fclose(self::$native);
    }
}
