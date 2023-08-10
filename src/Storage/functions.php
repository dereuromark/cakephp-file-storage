<?php

declare(strict_types = 1);

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
 * @throws \RuntimeException
 * @return resource
 */
function fopen(string $filename, string $mode, bool $useIncludePath = true, $context = null)
{
    if (is_resource($context)) {
        $result = fopen($filename, $mode, $useIncludePath, $context);
    } else {
        $result = fopen($filename, $mode, $useIncludePath);
    }

    if ($result === false) {
        throw new RuntimeException(sprintf(
            'Failed to open resource `%s`',
            $filename,
        ));
    }

    return $result;
}
