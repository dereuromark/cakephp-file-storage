<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\AdapterInterface;

/**
 * Factory Interface
 */
interface FactoryInterface
{
    public function className(): string;

    public function alias(): string;

    public function build(array $config): AdapterInterface;

    public function availabilityCheck(): void;
}
