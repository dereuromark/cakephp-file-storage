<?php

declare(strict_types = 1);

namespace FileStorage\Storage\PathBuilder;

use FileStorage\Storage\FileInterface;

/**
 * PathBuilderInterface
 */
interface PathBuilderInterface
{
    /**
     * Builds the path under which the data gets stored in the storage adapter.
     *
     * @param \FileStorage\Storage\FileInterface $file
     * @param array $options
     *
     * @return string
     */
    public function path(FileInterface $file, array $options = []): string;

    /**
     * Builds the path for a manipulated version of the file.
     *
     * This can be thumbnail of an image or a few different versions of a video.
     *
     * @param \FileStorage\Storage\FileInterface $file
     * @param string $name Name of the operation
     * @param array $options
     *
     * @return string
     */
    public function pathForVariant(FileInterface $file, string $name, array $options = []): string;
}
