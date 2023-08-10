<?php

declare(strict_types = 1);

namespace FileStorage\Storage;

use ArrayIterator;
use Iterator;
use League\Flysystem\AdapterInterface;
use RuntimeException;

/**
 * Adapter Collection
 */
class AdapterCollection implements AdapterCollectionInterface
{
    /**
     * @var array
     */
    protected array $adapters = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->adapters = [];
    }

    /**
     * @param string $name Name
     * @param \League\Flysystem\AdapterInterface $adapter Adapter
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function add($name, AdapterInterface $adapter)
    {
        if ($this->has($name)) {
            throw new RuntimeException(sprintf(
                'An adapter with the name `%s` already exists in the collection',
                $name,
            ));
        }

        $this->adapters[$name] = $adapter;
    }

    /**
     * @param string $name Name
     *
     * @return void
     */
    public function remove(string $name): void
    {
        unset($this->adapters[$name]);
    }

    /**
     * @param string $name Name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->adapters[$name]);
    }

    /**
     * @param string $name Name
     *
     * @throws \RuntimeException
     *
     * @return \League\Flysystem\AdapterInterface
     */
    public function get(string $name): AdapterInterface
    {
        if (!$this->has($name)) {
            throw new RuntimeException(sprintf(
                'A factory registered with the name `%s` is not part of the collection.',
                $name,
            ));
        }

        return $this->adapters[$name];
    }

    /**
     * Empties the collection
     *
     * @return void
     */
    public function empty(): void
    {
        unset($this->adapters);
    }

    /**
     * @return array
     */
    public function getNameToClassmap(): array
    {
        if (!$this->adapters) {
            return [];
        }

        $map = [];
        foreach ($this->adapters as $name => $object) {
            $map[$name] = get_class($object);
        }

        return $map;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->adapters);
    }
}
