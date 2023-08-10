<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor\Exception;

/**
 * ManipulationExistsException
 */
class VariantExistsException extends VariantException
{
    /**
     * @param string $name Name
     *
     * @return self
     */
    public static function withName(string $name): self
    {
        return new self(sprintf(
            'A variant with the name `%s` already exists',
            $name,
        ));
    }
}
