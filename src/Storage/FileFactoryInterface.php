<?php

declare(strict_types = 1);

namespace FileStorage\Storage;

use Psr\Http\Message\UploadedFileInterface;

/**
 * File Factory Interface
 */
interface FileFactoryInterface
{
    /**
     * Create a file storage object from the PSR interface
     *
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile PSR Uploaded File
     * @param string $storage Storage to use
     *
     * @return \FileStorage\Storage\FileInterface
     */
    public static function fromUploadedFile(
        UploadedFileInterface $uploadedFile,
        string $storage
    ): FileInterface;

    /**
     * From local disk
     *
     * @param string $path Path to local file
     * @param string $storage Storage
     *
     * @return \FileStorage\Storage\FileInterface
     */
    public static function fromDisk(string $path, string $storage): FileInterface;
}
