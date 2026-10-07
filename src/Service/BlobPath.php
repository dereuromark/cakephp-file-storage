<?php declare(strict_types=1);

namespace FileStorage\Service;

/**
 * Checks paths reserved for blob content.
 *
 * @author Mark Scherer
 * @license MIT
 */
final class BlobPath
{
    public static function check(string $path, string $root, string $hash, bool $strict = false): ?string
    {
        $original = $path;
        $path = str_replace('\\', '/', $path);
        if (
            $root === '' || !str_starts_with($path, $root . '/') || in_array('..', explode('/', $path), true)
            || ($strict && in_array('.', explode('/', $path), true))
        ) {
            return 'Stored blob path is outside FileStorage.deduplicate.root.';
        }
        if (pathinfo($strict ? $original : $path, PATHINFO_FILENAME) !== $hash) {
            return 'Stored blob path is not named by its hash. Check hashPathTemplate.';
        }
        if (str_starts_with($path, $root . '/.tmp/')) {
            return 'Stored blob path lies in the temporary directory of the blob root. Check hashPathTemplate.';
        }

        return null;
    }
}
