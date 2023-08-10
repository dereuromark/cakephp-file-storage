<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Exception;

/**
 * AdapterNotSupportedException
 */
class AdapterFactoryNotFoundException extends StorageException
{
    /**
     * @param string $name Name
     *
     * @return self
     */
    public static function fromName(string $name): self
    {
        return new self(sprintf(
            'Adapter factory `%s` was not found',
            $name,
        ));
    }
}
