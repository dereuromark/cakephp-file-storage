<?php declare(strict_types=1);

namespace FileStorage\Model\Validation;

use Psr\Http\Message\UploadedFileInterface;

trait ImageValidationTrait
{
    /**
     * Check if the uploaded file is a valid image.
     *
     * Use this validation before dimension checks to provide a clear error
     * message when non-image files are uploaded.
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param array<int> $allowedTypes Allowed image types (IMAGETYPE_* constants). Defaults to JPEG, PNG, GIF, WebP.
     *
     * @return bool Success
     */
    public static function isValidImage($check, array $allowedTypes = []): bool
    {
        if ($check instanceof UploadedFileInterface) {
            $file = $check->getStream()->getMetadata('uri');
        } else {
            if (!isset($check['tmp_name']) || !strlen($check['tmp_name'])) {
                return false;
            }
            $file = $check['tmp_name'];
        }

        $imageInfo = @getimagesize($file);
        if ($imageInfo === false) {
            return false;
        }

        if (!$allowedTypes) {
            $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        }

        return in_array($imageInfo[2], $allowedTypes, true);
    }

    /**
     * Check that the file is above the minimum width requirement
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param int $width Width of Image
     *
     * @return bool Success
     */
    public static function isAboveMinWidth($check, int $width): bool
    {
        if ($check instanceof UploadedFileInterface) {
            $file = $check->getStream()->getMetadata('uri');
        } else {
            // Non-file uploads also mean the height is too big
            if (!isset($check['tmp_name']) || !strlen($check['tmp_name'])) {
                return false;
            }
            $file = $check['tmp_name'];
        }
        $imageSize = getimagesize($file);
        if ($imageSize === false) {
            return false;
        }

        return $width > 0 && $imageSize[0] >= $width;
    }

    /**
     * Check that the file is below the maximum width requirement
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param int $width Width of Image
     *
     * @return bool Success
     */
    public static function isBelowMaxWidth($check, int $width): bool
    {
        if ($check instanceof UploadedFileInterface) {
            $file = $check->getStream()->getMetadata('uri');
        } else {
            // Non-file uploads also mean the height is too big
            if (!isset($check['tmp_name']) || !strlen($check['tmp_name'])) {
                return false;
            }

            $file = $check['tmp_name'];
        }
        $imageSize = getimagesize($file);
        if ($imageSize === false) {
            return false;
        }

        return $width > 0 && $imageSize[0] <= $width;
    }

    /**
     * Check that the file is above the minimum height requirement
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param int $height Height of Image
     *
     * @return bool Success
     */
    public static function isAboveMinHeight($check, int $height): bool
    {
        if ($check instanceof UploadedFileInterface) {
            $file = $check->getStream()->getMetadata('uri');
        } else {
            // Non-file uploads also mean the height is too big
            if (!isset($check['tmp_name']) || !strlen($check['tmp_name'])) {
                return false;
            }
            $file = $check['tmp_name'];
        }
        $imageSize = getimagesize($file);
        if ($imageSize === false) {
            return false;
        }

        return $height > 0 && $imageSize[1] >= $height;
    }

    /**
     * Check that the file is below the maximum height requirement
     *
     * @param \Psr\Http\Message\UploadedFileInterface|array $check Value to check
     * @param int $height Height of Image
     *
     * @return bool Success
     */
    public static function isBelowMaxHeight($check, int $height): bool
    {
        if ($check instanceof UploadedFileInterface) {
            $file = $check->getStream()->getMetadata('uri');
        } else {
            // Non-file uploads also mean the height is too big
            if (!isset($check['tmp_name']) || !strlen($check['tmp_name'])) {
                return false;
            }
            $file = $check['tmp_name'];
        }
        $imageSize = getimagesize($file);
        if ($imageSize === false) {
            return false;
        }

        return $height > 0 && $imageSize[1] <= $height;
    }
}
