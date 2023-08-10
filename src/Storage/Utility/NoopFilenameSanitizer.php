<?php

declare(strict_types = 1);

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
     *
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
     *
     * @return string
     */
    public function beautify(string $filename): string
    {
        return $filename;
    }
}
