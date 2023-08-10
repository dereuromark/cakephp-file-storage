<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Exception;

/**
 * Storage Exception
 */
class FileNotReadableException extends StorageException
{
    public static function filename(string $file): self
    {
        return new self(sprintf(
            'File %s is not readable',
            $file,
        ));
    }
}
