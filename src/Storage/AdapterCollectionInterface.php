<?php

declare(strict_types = 1);

namespace FileStorage\Storage;

use IteratorAggregate;
use League\Flysystem\AdapterInterface;

/**
 * Factory Collection Interface
 */
interface AdapterCollectionInterface extends IteratorAggregate
{
    /**
     * @param string $name Name
     * @param \League\Flysystem\AdapterInterface $adapter Adapter
     *
     * @return void
     */
    public function add($name, AdapterInterface $adapter);

    /**
     * @param string $name Name
     *
     * @return void
     */
    public function remove(string $name): void;

    /**
     * @param string $name Name
     *
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * @param string $name
     *
     * @return \League\Flysystem\AdapterInterface
     */
    public function get(string $name): AdapterInterface;

    /**
     * Empties the collection
     *
     * @return void
     */
    public function empty(): void;
}
