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

namespace FileStorage\Storage\Utility;

/**
 * FilenameSanitizerInterface
 */
interface FilenameSanitizerInterface
{
    /**
     * Removes or replaces non alphanumeric chars, asserts length.
     *
     * @param string $filename Filename
     * @return string
     */
    public function sanitize(string $filename): string;

    /**
     * Beautifies a filename to make it better to read.
     *
     * @param string $filename Filename
     * @return string
     */
    public function beautify(string $filename): string;
}
