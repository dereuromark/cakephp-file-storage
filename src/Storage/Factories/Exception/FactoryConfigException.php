<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories\Exception;

use FileStorage\Storage\Factories\FactoryInterface;

/**
 * FactoryConfigException
 */
class FactoryConfigException extends FactoryException
{
    public static function withMissingKey(string $key, FactoryInterface $factory)
    {
        return new self(sprintf(
            'Config key `%s` for `%s` is empty or missing',
            $key,
            get_class($factory),
        ));
    }
}
