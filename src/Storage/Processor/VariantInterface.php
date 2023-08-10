<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor;

/**
 * Manipulator Interface
 */
interface VariantInterface
{
    /**
     * @return string
     */
    public function name(): string;
}
