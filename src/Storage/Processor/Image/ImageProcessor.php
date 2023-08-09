<?php

/**
 * Copyright (c) Florian Krämer (https://florian-kraemer.net)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Florian Krämer (https://florian-kraemer.net)
 * @author    Florian Krämer
 * @link      https://github.com/Phauthentic
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace FileStorage\Storage\Processor\Image;

use GuzzleHttp\Psr7\StreamWrapper;
use http\Exception\InvalidArgumentException;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use League\Flysystem\Config;
use FileStorage\Storage\FileInterface;
use FileStorage\Storage\Processor\Image\Exception\TempFileCreationFailedException;
use FileStorage\Storage\PathBuilder\PathBuilderInterface;
use FileStorage\Storage\FileStorageInterface;
use FileStorage\Storage\Processor\ProcessorInterface;
use FileStorage\Storage\UrlBuilder\UrlBuilderInterface;
use FileStorage\Storage\Utility\TemporaryFile;

use function FileStorage\Storage\fopen;

/**
 * Image Operator
 */
class ImageProcessor implements ProcessorInterface
{
    use OptimizerTrait;

    /**
     * @var array<int, string>
     */
    protected array $mimeTypes = [
        'image/gif',
        'image/jpg',
        'image/jpeg',
        'image/png'
    ];

    /**
     * @var array<int, string>
     */
    protected array $processOnlyTheseVariants = [];

    /**
     * @var \FileStorage\Storage\FileStorageInterface
     */
    protected FileStorageInterface $storageHandler;

    /**
     * @var \FileStorage\Storage\PathBuilder\PathBuilderInterface
     */
    protected PathBuilderInterface $pathBuilder;

    /**
     * @var \FileStorage\Storage\UrlBuilder\UrlBuilderInterface
     */
    protected ?UrlBuilderInterface $urlBuilder;

    /**
     * @var \Intervention\Image\ImageManager
     */
    protected ImageManager $imageManager;

    /**
     * @var \Intervention\Image\Image
     */
    protected Image $image;

    /**
     * Quality setting for writing images
     *
     * @var int
     */
    protected int $quality = 90;

    /**
     * @param \FileStorage\Storage\FileStorageInterface $storageHandler File Storage Handler
     * @param \FileStorage\Storage\PathBuilder\PathBuilderInterface $pathBuilder Path Builder
     * @param \Intervention\Image\ImageManager $imageManager Image Manager
     */
    public function __construct(
        FileStorageInterface $storageHandler,
        PathBuilderInterface $pathBuilder,
        ImageManager $imageManager,
        ?UrlBuilderInterface $urlBuilder = null
    ) {
        $this->storageHandler = $storageHandler;
        $this->pathBuilder = $pathBuilder;
        $this->imageManager = $imageManager;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param array<int, string> $mimeTypes Mime Type List
     * @return $this
     */
    protected function setMimeTypes(array $mimeTypes): self
    {
        $this->mimeTypes = $mimeTypes;

        return $this;
    }

    /**
     * @param int $quality Quality
     * @return $this
     */
    public function setQuality(int $quality): self
    {
        if ($quality > 100 || $quality <= 0) {
            throw new InvalidArgumentException(sprintf(
                'Quality has to be a positive integer between 1 and 100. %s was provided',
                (string)$quality
            ));
        }

        $this->quality = $quality;

        return $this;
    }

    /**
     * @param \FileStorage\Storage\FileInterface $file File
     * @return bool
     */
    protected function isApplicable(FileInterface $file): bool
    {
        return $file->hasVariants()
            && in_array($file->mimeType(), $this->mimeTypes, true);
    }

    /**
     * @param array<int, string> $variants Variants by name
     * @return $this
     */
    public function processOnlyTheseVariants(array $variants): self
    {
        $this->processOnlyTheseVariants = $variants;

        return $this;
    }

    /**
     * @return $this
     */
    public function processAll(): self
    {
        $this->processOnlyTheseVariants = [];

        return $this;
    }

    /**
     * Read the data from the files resource if (still) present,
     * if not fetch it from the storage backend and write the data
     * to the stream of the temp file
     *
     * @param \FileStorage\Storage\FileInterface $file File
     * @param resource $tempFileStream Temp File Stream Resource
     * @return int|bool False on error
     */
    protected function copyOriginalFileData(FileInterface $file, $tempFileStream)
    {
        $stream = $file->resource();
        $storage = $this->storageHandler->getStorage($file->storage());

        if ($stream === null) {
            $stream = $storage->readStream($file->path());
            $stream = $stream['stream'];
        } else {
            rewind($stream);
        }
        $result = stream_copy_to_stream(
            $stream,
            $tempFileStream
        );
        fclose($tempFileStream);

        return $result;
    }

    /**
     * @param string $variant Variant name
     * @param array<string, mixed> $variantData Variant data
     * @return bool
     */
    protected function shouldProcessVariant(string $variant, array $variantData): bool
    {
        return !(
            // Empty operations
            empty($variantData['operations'])
            || (
                // Check if the operation should be processed
                !empty($this->processOnlyTheseVariants)
                && !in_array($variant, $this->processOnlyTheseVariants, true)
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function process(FileInterface $file): FileInterface
    {
        if (!$this->isApplicable($file)) {
            return $file;
        }

        $storage = $this->storageHandler->getStorage($file->storage());

        // Create a local tmp file on the processing system / machine
        $tempFile = TemporaryFile::create();
        $tempFileStream = fopen($tempFile, 'wb+');

        // Read the data from the files resource if (still) present,
        // if not fetch it from the storage backend and write the data
        // to the stream of the temp file
        $result = $this->copyOriginalFileData($file, $tempFileStream);

        // Stop if the temp file could not be generated
        if ($result === false) {
            throw TempFileCreationFailedException::withFilename($tempFile);
        }

        // Iterate over the variants described as an array
        foreach ($file->variants() as $variant => $data) {
            if (!$this->shouldProcessVariant($variant, $data)) {
                continue;
            }

            $this->image = $this->imageManager->make($tempFile);
            $operations = new Operations($this->image);

            // Apply the operations
            foreach ($data['operations'] as $operation => $arguments) {
                $operations->{$operation}($arguments);
            }

            $path = $this->pathBuilder->pathForVariant($file, $variant);

            if (isset($data['optimize']) && $data['optimize'] === true) {
                $this->optimizeAndStore($file, $path);
            } else {
                $storage->writeStream(
                    $path,
                    StreamWrapper::getResource($this->image->stream($file->extension(), $this->quality)),
                    new Config()
                );
            }

            $data['path'] = $path;
            $file = $file->withVariant($variant, $data);

            if ($this->urlBuilder !== null) {
                $data['url'] = $this->urlBuilder->urlForVariant($file, $variant);
            }

            $file = $file->withVariant($variant, $data);
        }

        unlink($tempFile);

        return $file;
    }

    /**
     * @param \FileStorage\Storage\FileInterface $file File
     * @param string $path Path
     * @return void
     */
    protected function optimizeAndStore(FileInterface $file, string $path): void
    {
        $storage = $this->storageHandler->getStorage($file->storage());

        // We need more tmp files because the optimizer likes to write
        // and read the files from disk, not from a stream. :(
        $optimizerTempFile = TemporaryFile::create();
        $optimizerOutput = TemporaryFile::create();

        // Save the image to the tmp file
        $this->image->save($optimizerTempFile, 90, $file->extension());
        // Optimize it and write it to another file
        $this->optimizer()->optimize($optimizerTempFile, $optimizerOutput);
        // Open a new stream for the storage system
        $optimizerOutputHandler = fopen($optimizerOutput, 'rb+');

        // And store it...
        $storage->writeStream(
            $path,
            $optimizerOutputHandler,
            new Config()
        );

        // Cleanup
        fclose($optimizerOutputHandler);
        unlink($optimizerTempFile);
        unlink($optimizerOutput);

        // Cleanup
        unset(
            $optimizerOutputHandler,
            $optimizerTempFile,
            $optimizerOutput
        );
    }
}
