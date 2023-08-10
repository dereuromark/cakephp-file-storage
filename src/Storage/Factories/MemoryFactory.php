<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Memory\MemoryAdapter;

/**
 * Memory
 */
class MemoryFactory extends AbstractFactory
{
    protected string $alias = 'memory';

    protected ?string $package = 'league/flysystem-memory';

    protected string $className = MemoryAdapter::class;

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        return new MemoryAdapter();
    }
}
