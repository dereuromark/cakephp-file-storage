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

namespace FileStorage\Storage\Factories\Exception;

use FileStorage\Storage\Exception\StorageException;
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
            get_class($factory)
        ));
    }
}
