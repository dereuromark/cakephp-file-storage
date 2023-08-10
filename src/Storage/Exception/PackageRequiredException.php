<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Exception;

/**
 * PackageRequiredException
 */
class PackageRequiredException extends StorageException
{
    /**
     * @param string $adapter Adapter
     * @param string $package Package
     *
     * @return self
     */
    public static function fromAdapterAndPackageNames(string $adapter, string $package): self
    {
        return new self(sprintf(
            'Adapter `%s` requires package `%s`',
            $adapter,
            $package,
        ));
    }
}
