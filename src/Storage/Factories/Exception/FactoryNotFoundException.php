<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories\Exception;

/**
 * FactoryNotFoundException
 */
class FactoryNotFoundException extends FactoryException
{
    /**
     * @param string $name Name
     *
     * @return self
     */
    public static function withName(string $name): self
    {
        return new self(sprintf(
            'No factory found for `%s`',
            $name,
        ));
    }
}
