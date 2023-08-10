<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor\Exception;

/**
 * VariantDoesNotExistException
 */
class VariantDoesNotExistException extends VariantException
{
    /**
     * @param string $name Name
     *
     * @return self
     */
    public static function withName(string $name): self
    {
        return new self(sprintf(
            'A variant with the name `%s` does not exists',
            $name,
        ));
    }
}
