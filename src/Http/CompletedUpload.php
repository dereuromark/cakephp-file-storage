<?php declare(strict_types=1);

namespace FileStorage\Http;

use Laminas\Diactoros\UploadedFile;

/**
 * Path-backed upload with a server-computed digest.
 *
 * @internal
 * @author Mark Scherer
 * @license MIT
 */
class CompletedUpload extends UploadedFile
{
    public function __construct(string $path, int $size, ?string $filename, ?string $filetype, public readonly string $digest)
    {
        parent::__construct($path, $size, UPLOAD_ERR_OK, $filename, $filetype);
    }
}
