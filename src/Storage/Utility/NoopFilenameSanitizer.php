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
 * Noop Filename Sanitizer
 *
 * @link https://en.wikipedia.org/wiki/NOP_(code)
 */
class NoopFilenameSanitizer implements FilenameSanitizerInterface
{
    /**
     * @param string $string String
     * @return string
     */
    public function sanitize(string $string): string
    {
        return $string;
    }

    /**
     * Beautifies a filename to make it better to read
     *
     * @param string $filename Filename
     * @return string
     */
    public function beautify(string $filename): string
    {
        return $filename;
    }
}
