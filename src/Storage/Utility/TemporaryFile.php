<?php

declare(strict_types = 1);

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
     *
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
