<?php

/**
 * Copyright (c) Florian Krämer (https://florian-kraemer.net)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Florian Krämer (https://florian-kraemer.net)
 * @author    Florian Krämer
 * @link      https://github.com/Phauthentic
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace FileStorage\Storage;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

/**
 * StorageServiceInterface
 */
interface StorageServiceInterface
{
    /**
     * Adapter Factory
     *
     * @return \FileStorage\Storage\StorageAdapterFactoryInterface
     */
    public function adapterFactory(): StorageAdapterFactoryInterface;

    /**
     * Get the adapter collection
     *
     * @return \FileStorage\Storage\AdapterCollectionInterface
     */
    public function adapters(): AdapterCollectionInterface;

    /**
     * Gets an adapter instance, lazy loads it as needed.
     *
     * @param string $name
     * @return \League\Flysystem\AdapterInterface
     */
    public function adapter(string $name): AdapterInterface;

    /**
     * Adds an adapter config
     *
     * @param string $name
     * @param string $class
     * @param array $options
     * @return void
     */
    public function addAdapterConfig(string $name, string $class, array $options);

    /**
     * Stores a resource in a storage backend
     *
     * @param string $adapter Adapter config name
     * @param string $path Path where the file is stored
     * @param resource $resource Resource to store
     * @param \League\Flysystem\Config|null $config
     * @return array
     */
    public function storeResource(string $adapter, string $path, $resource, ?Config $config = null): array;

    /**
     * Stores a file in a storage backend
     *
     * @param string $adapter Adapter config name
     * @param string $path Path where the file is stored
     * @param string $file File to store
     * @param \League\Flysystem\Config|null $config
     * @return array
     */
    public function storeFile(string $adapter, string $path, string $file, ?Config $config = null): array;

    /**
     * Checks if a file exists in a store
     *
     * @param string $adapter Adapter
     * @param string $path Path
     * @return bool
     */
    public function fileExists(string $adapter, string $path): bool;

    /**
     * Removes a file from a storage backend
     *
     * @param string $adapter Name
     * @param string $path File to delete
     * @return bool
     */
    public function removeFile(string $adapter, string $path): bool;
}
