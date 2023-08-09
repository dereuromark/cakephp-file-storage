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
 * Temporary File
 */
class TemporaryFile
{
    /**
     * @var string
     */
    protected static string $tempDir = '';

    /**
     * @param string $tempDir
     * @return void
     */
    public static function setTempFolder(string $tempDir): void
    {
        static::$tempDir = $tempDir;
    }

    /**
     * @return string
     */
    public static function tempDir(): string
    {
        if (static::$tempDir === '') {
            return sys_get_temp_dir();
        }

        return static::$tempDir;
    }

    /**
     * @return string
     */
    public static function create(): string
    {
        return tempnam(static::tempDir(), '');
    }
}
