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
     * @return \League\Flysystem\AdapterInterface
     */
    public function buildStorageAdapter(
        string $adapterClass,
        array $options
    ): AdapterInterface;
}
