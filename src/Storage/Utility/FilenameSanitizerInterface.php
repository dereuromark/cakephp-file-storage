<?php

declare(strict_types = 1);

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
     *
     * @return string
     */
    public function sanitize(string $filename): string;

    /**
     * Beautifies a filename to make it better to read.
     *
     * @param string $filename Filename
     *
     * @return string
     */
    public function beautify(string $filename): string;
}
