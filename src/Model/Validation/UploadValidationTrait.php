<?php declare(strict_types=1);

namespace FileStorage\Model\Validation;

use Cake\Utility\Hash;
use Psr\Http\Message\UploadedFileInterface;

trait UploadValidationTrait
{
    /**
     * Check that the file does not exceed the max
     * file size specified by PHP
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     *
     * @return bool Success
     */
    public static function isUnderPhpSizeLimit($check): bool
    {
        if ($check instanceof UploadedFileInterface) {
            return $check->getError() !== UPLOAD_ERR_INI_SIZE;
        }

        return Hash::get($check, 'error') !== UPLOAD_ERR_INI_SIZE;
    }

    /**
     * Check that the file does not exceed the max
     * file size specified in the HTML Form
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     *
     * @return bool Success
     */
    public static function isUnderFormSizeLimit($check): bool
    {
        if ($check instanceof UploadedFileInterface) {
            return $check->getError() !== UPLOAD_ERR_FORM_SIZE;
        }

        return Hash::get($check, 'error') !== UPLOAD_ERR_FORM_SIZE;
    }

    /**
     * Check that the file was completely uploaded
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     *
     * @return bool Success
     */
    public static function isCompletedUpload($check): bool
    {
        if ($check instanceof UploadedFileInterface) {
            return $check->getError() !== UPLOAD_ERR_PARTIAL;
        }

        return Hash::get($check, 'error') !== UPLOAD_ERR_PARTIAL;
    }

    /**
     * Check that a file was uploaded
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     *
     * @return bool Success
     */
    public static function isFileUpload($check): bool
    {
        if ($check instanceof UploadedFileInterface) {
            return $check->getError() !== UPLOAD_ERR_NO_FILE;
        }

        return Hash::get($check, 'error') !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Check that the file was successfully written to the server
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     *
     * @return bool Success
     */
    public static function isSuccessfulWrite($check): bool
    {
        if ($check instanceof UploadedFileInterface) {
            return $check->getError() !== UPLOAD_ERR_CANT_WRITE;
        }

        return Hash::get($check, 'error') !== UPLOAD_ERR_CANT_WRITE;
    }

    /**
     * Check that the file is above the minimum file upload size
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param int $size Minimum file size
     *
     * @return bool Success
     */
    public static function isAboveMinSize($check, $size): bool
    {
        if ($check instanceof UploadedFileInterface) {
            return $check->getSize() >= $size;
        }

        return !empty($check['size']) && $check['size'] >= $size;
    }

    /**
     * Check that the file is below the maximum file upload size
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param int $size Maximum file size
     *
     * @return bool Success
     */
    public static function isBelowMaxSize($check, $size): bool
    {
        if ($check instanceof UploadedFileInterface) {
            return $check->getSize() <= $size;
        }

        return !empty($check['size']) && $check['size'] <= $size;
    }

    /**
     * Check that the uploaded file's extension is in the given allow-list (case-insensitive).
     *
     * Reads the client-supplied filename, so callers SHOULD also pair this with
     * {@see self::hasAllowedMimeType()} (server-side sniffing) to defeat spoofing.
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param array<string> $extensions Allowed extensions, without the leading dot, e.g. `['jpg', 'png']`.
     *
     * @return bool
     */
    public static function hasAllowedExtension($check, array $extensions): bool
    {
        if (!$extensions) {
            return false;
        }

        if ($check instanceof UploadedFileInterface) {
            $name = $check->getClientFilename();
        } else {
            $name = $check['name'] ?? null;
        }
        if (!is_string($name) || $name === '') {
            return false;
        }

        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '') {
            return false;
        }

        $allowed = array_map(static fn (string $ext): string => strtolower(ltrim($ext, '.')), $extensions);

        return in_array($extension, $allowed, true);
    }

    /**
     * Check that the upload's MIME type is in the given allow-list.
     *
     * By default, sniffs the MIME type server-side via finfo against the actual file
     * contents — the client-supplied `Content-Type` header is user-controlled and not
     * trustworthy. Pass `$sniff = false` to fall back to the client header (only
     * acceptable when the file is already on a trusted path, e.g. tests).
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param array<string> $mimes Allowed MIME types, e.g. `['image/jpeg', 'image/png']`.
     * @param bool $sniff Whether to sniff the file contents server-side. Defaults to true.
     *
     * @return bool
     */
    public static function hasAllowedMimeType($check, array $mimes, bool $sniff = true): bool
    {
        if (!$mimes) {
            return false;
        }

        if ($check instanceof UploadedFileInterface) {
            $tmpName = null;
            $clientMime = $check->getClientMediaType();
        } else {
            $tmpName = $check['tmp_name'] ?? null;
            $clientMime = $check['type'] ?? null;
        }

        $allowed = array_map('strtolower', $mimes);

        if ($sniff && function_exists('finfo_open')) {
            $path = $tmpName;
            if ($check instanceof UploadedFileInterface) {
                $stream = $check->getStream();
                $meta = $stream->getMetadata('uri');
                if (is_string($meta)) {
                    $path = $meta;
                }
            }

            if (is_string($path) && $path !== '' && is_file($path)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    try {
                        $sniffed = finfo_file($finfo, $path);
                    } finally {
                        finfo_close($finfo);
                    }
                    if (is_string($sniffed) && $sniffed !== '') {
                        return in_array(strtolower($sniffed), $allowed, true);
                    }
                }
            }
        }

        if (!is_string($clientMime) || $clientMime === '') {
            return false;
        }

        return in_array(strtolower($clientMime), $allowed, true);
    }
}
