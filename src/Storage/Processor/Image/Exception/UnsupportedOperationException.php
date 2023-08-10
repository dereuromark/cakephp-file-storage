<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor\Image\Exception;

/**
 * UnsupportedOperationException
 */
final class UnsupportedOperationException extends ImageProcessingException
{
    /**
     * @param string $name Name
     *
     * @return self
     */
    public static function withName(string $name): self
    {
        return new self(sprintf(
            'Operation `%s` is not implemented or supported',
            $name,
        ));
    }
}
