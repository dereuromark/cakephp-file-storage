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

namespace FileStorage\Storage\Exception;

/**
 * PackageRequiredException
 */
class PackageRequiredException extends StorageException
{
    /**
     * @param string $adapter Adapter
     * @param string $package Package
     * @return self
     */
    public static function fromAdapterAndPackageNames(string $adapter, string $package): self
    {
        return new self(sprintf(
            'Adapter `%s` requires package `%s`',
            $adapter,
            $package
        ));
    }
}
