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

namespace FileStorage\Storage\Processor\Exception;

/**
 * VariantDoesNotExistException
 */
class VariantDoesNotExistException extends VariantException
{
    /**
     * @param string $name Name
     * @return self
     */
    public static function withName(string $name): self
    {
        return new self(sprintf(
            'A variant with the name `%s` does not exists',
            $name
        ));
    }
}
