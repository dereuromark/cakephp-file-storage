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
 * FileStorageInterface
 */
interface FileStorageInterface
{
    /**
     * Stores the file in the storage backend and provides the file entity
     * with a path after the file was stored.
     *
     * @param \FileStorage\Storage\FileInterface $file File
     * @param \League\Flysystem\Config|null $config Flysystem Config when storing a file
     * @return \FileStorage\Storage\FileInterface
     */
    public function store(FileInterface $file, ?Config $config = null): FileInterface;

    /**
     * Removes a file from the storage backend
     *
     * @param \FileStorage\Storage\FileInterface $file File
     * @return \FileStorage\Storage\FileInterface
     */
    public function remove(FileInterface $file): FileInterface;

    /**
     * @param \FileStorage\Storage\FileInterface $file File
     * @param string $name Name
     * @return \FileStorage\Storage\FileInterface
     */
    public function removeVariant(FileInterface $file, string $name): FileInterface;

    /**
     * Gets the storage abstraction to use
     *
     * @param string $storage Storage name to use
     * @return \League\Flysystem\AdapterInterface
     */
    public function getStorage(string $storage): AdapterInterface;
}
