<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor\Image\Exception;

/**
 * TempFileCreationFailedException
 */
class TempFileCreationFailedException extends ImageProcessingException
{
    /**
     * @param string $name Name
     *
     * @return self
     */
    public static function withFilename(string $name): self
    {
        return new self(sprintf(
            'Failed to create `%s`',
            $name,
        ));
    }
}
