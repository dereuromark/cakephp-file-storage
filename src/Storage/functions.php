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

use RuntimeException;

/**
 * The original php function has mixed return values, either a resource or
 * boolean false. But we want an exception.
 *
 * @param string $filename
 * @param string $mode
 * @param bool $useIncludePath
 * @param mixed $context
 * @return resource
 */
function fopen(string $filename, string $mode, bool $useIncludePath = true, $context = null)
{
    if (is_resource($context)) {
        $result = \fopen($filename, $mode, $useIncludePath, $context);
    } else {
        $result = \fopen($filename, $mode, $useIncludePath);
    }

    if ($result === false) {
        throw new RuntimeException(sprintf(
            'Failed to open resource `%s`',
            $filename
        ));
    }

    return $result;
}
