<?php

declare(strict_types = 1);

namespace FileStorage\Storage;

use FileStorage\Storage\Exception\StorageException;
use FileStorage\Storage\Factories\Exception\FactoryNotFoundException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use RuntimeException;

/**
 * StorageFactory - Manages and instantiates storage engine adapters.
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 */
class StorageService implements StorageServiceInterface
{
    /**
     * @var array
     */
    protected array $adapterConfig = [];

    /**
     * @var \FileStorage\Storage\AdapterCollectionInterface
     */
    protected AdapterCollectionInterface $adapterCollection;

    /**
     * @var \FileStorage\Storage\StorageAdapterFactoryInterface
     */
    protected StorageAdapterFactoryInterface $adapterFactory;

    /**
     * Constructor
     *
     * @param \FileStorage\Storage\StorageAdapterFactoryInterface $adapterFactory Adapter Factory
     * @param \FileStorage\Storage\AdapterCollectionInterface|null $factoryCollection Factory Collection
     */
    public function __construct(
        StorageAdapterFactoryInterface $adapterFactory,
        ?AdapterCollectionInterface $factoryCollection = null
    ) {
        $this->adapterFactory = $adapterFactory;
        $this->adapterCollection = $factoryCollection ?? new AdapterCollection();
    }

    /**
     * Adapter Factory
     *
     * @return \FileStorage\Storage\StorageAdapterFactoryInterface
     */
    public function adapterFactory(): StorageAdapterFactoryInterface
    {
        return $this->adapterFactory;
    }

    /**
     * @inheritDoc
     */
    public function adapters(): AdapterCollectionInterface
    {
        return $this->adapterCollection;
    }

    /**
     * @inheritDoc
     *
     * @throws \FileStorage\Storage\Factories\Exception\FactoryNotFoundException
     */
    public function adapter(string $name): AdapterInterface
    {
        if ($this->adapterCollection->has($name)) {
            return $this->adapterCollection->get($name);
        }

        if (!isset($this->adapterConfig[$name])) {
            throw FactoryNotFoundException::withName($name);
        }

        $options = $this->adapterConfig[$name];

        return $this->loadAdapter($name, $options['class'], $options['options']);
    }

    /**
     * Loads an adapter instance using the factory
     *
     * @param string $name Name
     * @param string $adapter Adapter
     * @param array $options
     *
     * @return \League\Flysystem\AdapterInterface
     */
    public function loadAdapter(string $name, string $adapter, array $options): AdapterInterface
    {
        $adapter = $this->adapterFactory->buildStorageAdapter(
            $adapter,
            $options,
        );

        $this->adapterCollection->add($name, $adapter);

        return $adapter;
    }

    /**
     * Adds an adapter config
     *
     * @param string $name
     * @param string $class
     * @param array $options
     *
     * @return void
     */
    public function addAdapterConfig(string $name, string $class, array $options)
    {
        $this->adapterConfig[$name] = [
            'class' => $class,
            'options' => $options,
        ];
    }

    /**
     * Sets the adapter configuration to lazy load them later
     *
     * @param array $config Config
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function setAdapterConfigFromArray(array $config): void
    {
        foreach ($config as $name => $options) {
            if (!isset($options['class'])) {
                throw new RuntimeException('Adapter class or name is missing');
            }

            if (!isset($options['options']) || !is_array($options['options'])) {
                throw new RuntimeException('Adapter options must be an array');
            }

            $this->adapterConfig[$name] = $options;
        }
    }

    /**
     * @param \League\Flysystem\Config|null $config Config
     *
     * @return \League\Flysystem\Config
     */
    protected function makeConfigIfNeeded(?Config $config)
    {
        if ($config === null) {
            $config = new Config();
        }

        return $config;
    }

    /**
     * @inheritDoc
     *
     * @throws \FileStorage\Storage\Exception\StorageException
     */
    public function storeResource(string $adapter, string $path, $resource, ?Config $config = null): array
    {
        $config = $this->makeConfigIfNeeded($config);
        $result = $this->adapter($adapter)->writeStream($path, $resource, $config);

        if ($result === false) {
            throw new StorageException(sprintf(
                'Failed to store resource stream to in `%s` with path `%s`',
                $adapter,
                $path,
            ));
        }

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @throws \FileStorage\Storage\Exception\StorageException
     */
    public function storeFile(string $adapter, string $path, string $file, ?Config $config = null): array
    {
        $config = $this->makeConfigIfNeeded($config);
        $result = $this->adapter($adapter)->write($path, file_get_contents($file), $config);

        if ($result === false) {
            throw new StorageException(sprintf(
                'Failed to store file `%s` in `%s` with path `%s`',
                $file,
                $adapter,
                $path,
            ));
        }

        return $result;
    }

    /**
     * @param string $adapter Adapter
     * @param string $path Path
     *
     * @return bool
     */
    public function fileExists(string $adapter, string $path): bool
    {
        return $this->adapter($adapter)->has($path);
    }

    /**
     * @param string $adapter Name
     * @param string $path File to delete
     *
     * @return bool
     */
    public function removeFile(string $adapter, string $path): bool
    {
        return $this->adapter($adapter)->delete($path);
    }
}
