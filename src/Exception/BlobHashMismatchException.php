<?php declare(strict_types=1);

namespace FileStorage\Exception;

use RuntimeException;

/**
 * Stored content differs from its expected hash.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobHashMismatchException extends RuntimeException
{
}
