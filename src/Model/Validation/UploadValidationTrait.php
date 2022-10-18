<?php

declare(strict_types = 1);

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
}
