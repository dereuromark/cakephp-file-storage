<?php

declare(strict_types = 1);

namespace FileStorage\Storage;

use League\Flysystem\AdapterInterface;

/**
 * StorageFactory - Manages and instantiates storage engine adapters.
 */
interface StorageAdapterFactoryInterface
{
    /**
     * Instantiates Flystem adapters.
     *
     * @param string $adapterClass Adapter alias or classname
     * @param array $options Options array
     *
     * @return \League\Flysystem\AdapterInterface
     */
    public function buildStorageAdapter(
        string $adapterClass,
        array $options
    ): AdapterInterface;
}
