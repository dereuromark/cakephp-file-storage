<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor;

use FileStorage\Storage\FileInterface;

/**
 * Processor Interface
 */
interface ProcessorInterface
{
    /**
     * @param \FileStorage\Storage\FileInterface $file File
     *
     * @return \FileStorage\Storage\FileInterface
     */
    public function process(FileInterface $file): FileInterface;
}
