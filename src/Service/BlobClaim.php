<?php declare(strict_types=1);

namespace FileStorage\Service;

/**
 * Locked blob identity and stored path.
 *
 * @author Mark Scherer
 * @license MIT
 */
final class BlobClaim
{
    public function __construct(public readonly int $id, public readonly ?string $path)
    {
    }
}
