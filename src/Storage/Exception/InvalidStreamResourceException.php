<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Exception;

/**
 * Invalid Stream Resource
 */
class InvalidStreamResourceException extends StorageException
{
    /**
     * @return self
     */
    public static function create(): self
    {
        return new self(
            'The provided value is not a valid stream resource',
        );
    }
}
