<?php

declare(strict_types = 1);

namespace FileStorage\Storage;

use FileStorage\Storage\Exception\FileDoesNotExistException;
use FileStorage\Storage\Exception\FileNotReadableException;
use FileStorage\Storage\Utility\MimeType;
use FileStorage\Storage\Utility\PathInfo;
use FileStorage\Storage\Utility\StreamWrapper;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * File Factory
 */
class FileFactory implements FileFactoryInterface
{
    /**
     * @inheritDoc
     */
    public static function fromUploadedFile(
        UploadedFileInterface $uploadedFile,
        string $storage
    ): FileInterface {
        static::checkUploadedFile($uploadedFile);

        $file = File::create(
            (string)$uploadedFile->getClientFilename(),
            (int)$uploadedFile->getSize(),
            (string)$uploadedFile->getClientMediaType(),
            $storage,
        );

        return $file->withResource(
            StreamWrapper::getResource($uploadedFile->getStream()),
        );
    }

    /**
     * @inheritDoc
     */
    public static function fromDisk(string $path, string $storage): FileInterface
    {
        static::checkFile($path);

        $info = PathInfo::for($path);
        $filesize = filesize($path);
        $mimeType = MimeType::byExtension($info->extension());

        $file = File::create(
            $info->basename(),
            $filesize,
            $mimeType,
            $storage,
        );

        $resource = fopen($path, 'rb');

        return $file->withResource($resource);
    }

    /**
     * Checks if the uploaded file is a valid upload
     *
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile Uploaded File
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected static function checkUploadedFile(UploadedFileInterface $uploadedFile): void
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException(sprintf(
                'Can\'t create storage object from upload with error code: %d',
                $uploadedFile->getError(),
            ));
        }
    }

    /**
     * @param string $path Path
     *
     * @throws \FileStorage\Storage\Exception\FileDoesNotExistException
     * @throws \FileStorage\Storage\Exception\FileNotReadableException
     *
     * @return void
     */
    protected static function checkFile(string $path): void
    {
        if (!file_exists($path)) {
            throw FileDoesNotExistException::filename($path);
        }

        if (!is_readable($path)) {
            throw FileNotReadableException::filename($path);
        }
    }
}
