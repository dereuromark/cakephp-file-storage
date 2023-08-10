<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Exception;

/**
 * Storage Exception
 */
class FileDoesNotExistException extends StorageException
{
    /**
     * @param string $file
     *
     * @return self
     */
    public static function filename(string $file): self
    {
        return new self(sprintf(
            'File %s does not exist',
            $file,
        ));
    }
}
