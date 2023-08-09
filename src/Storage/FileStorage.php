<?php

/**
 * Copyright (c) Florian Krämer (https://florian-kraemer.net)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Florian Krämer (https://florian-kraemer.net)
 * @author    Florian Krämer
 * @link      https://github.com/Phauthentic
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace FileStorage\Storage;

use InvalidArgumentException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use FileStorage\Storage\PathBuilder\PathBuilderInterface;
use FileStorage\Storage\Processor\Exception\VariantDoesNotExistException;
use FileStorage\Storage\Processor\Exception\VariantException;
use FileStorage\Storage\UrlBuilder\UrlBuilderInterface;
use RuntimeException;

/**
 * File Storage
 */
class FileStorage implements FileStorageInterface
{
    /**
     * @var array
     */
    protected array $callbacks = [
        'beforeSave' => [],
        'afterSave' => [],
        'beforeRemove' => [],
        'afterRemove' => [],
    ];

    /**
     * @var \FileStorage\Storage\PathBuilder\PathBuilderInterface|null
     */
    protected ?PathBuilderInterface $pathBuilder;

    /**
     * @var \FileStorage\Storage\UrlBuilder\UrlBuilderInterface|null
     */
    protected ?UrlBuilderInterface $urlBuilder;

    /**
     * @var \FileStorage\Storage\StorageServiceInterface
     */
    protected StorageServiceInterface $storageService;

    /**
     * Constructor
     *
     * @param \FileStorage\Storage\StorageServiceInterface $storageService Storage Service
     * @param \FileStorage\Storage\PathBuilder\PathBuilderInterface|null $pathBuilder Path Builder
     * @param \FileStorage\Storage\UrlBuilder\UrlBuilderInterface|null $urlBuilder Path Builder
     */
    public function __construct(
        StorageServiceInterface $storageService,
        ?PathBuilderInterface $pathBuilder = null,
        ?UrlBuilderInterface $urlBuilder = null
    ) {
        $this->storageService = $storageService;
        $this->pathBuilder = $pathBuilder;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param string $name Name of the callback
     * @return void
     */
    protected function checkCallbackName(string $name): void
    {
        if (!array_key_exists($name, $this->callbacks)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid callback `%s`, only %s are valid',
                $name,
                implode(', ', array_keys($this->callbacks))
            ));
        }
    }

    /**
     * Adds a callback
     *
     * @param string $name
     * @param callable $callable Callable
     * @return void
     */
    public function addCallback($name, callable $callable): void
    {
        $this->checkCallbackName($name);
        $this->callbacks[$name][] = $callable;
    }

    /**
     * @param string $name Name of the callback
     * @param \FileStorage\Storage\FileInterface $file File
     * @return \FileStorage\Storage\FileInterface
     */
    public function runCallbacks(string $name, FileInterface $file): FileInterface
    {
        $this->checkCallbackName($name);

        foreach ($this->callbacks[$name] as $callback) {
            $file = $callback($file);
        }

        return $file;
    }

    /**
     * @inheritDoc
     * @throws \RuntimeException
     */
    public function store(FileInterface $file, ?Config $config = null): FileInterface
    {
        if ($config === null) {
            $config = new Config();
        }

        if ($this->pathBuilder !== null) {
            $file = $file->buildPath($this->pathBuilder);
        }

        if ($this->urlBuilder !== null) {
            $file = $file->buildUrl($this->urlBuilder);
        }

        $file = $this->runCallbacks('beforeSave', $file);

        $storage = $this->getStorage($file->storage());
        $resource = $file->resource();
        if ($resource === null) {
            throw new RuntimeException('No resource given');
        }
        $storage->writeStream($file->path(), $resource, $config);

        return $this->runCallbacks('afterSave', $file);
    }

    /**
     * @inheritDoc
     */
    public function remove(FileInterface $file): FileInterface
    {
        $file = $this->runCallbacks('beforeRemove', $file);

        // Delete all variants of the file
        foreach ($file->variants() as $variant) {
            if (!empty($variant['path'])) {
                $this->getStorage($file->storage())->delete($variant['path']);
            }
        }

        // Delete the file
        $this->getStorage($file->storage())->delete($file->path());

        return $this->runCallbacks('afterRemove', $file);
    }

    /**
     * @inheritDoc
     */
    public function removeVariant(FileInterface $file, string $name): FileInterface
    {
        if (!$file->hasVariant($name)) {
            throw VariantDoesNotExistException::withName($name);
        }

        $variant = $file->variant($name);
        if (empty($variant['path'])) {
            throw new VariantException(sprintf(
                'Variant `%s` is missing a path',
                $name
            ));
        }

        $this->getStorage($file->storage())->delete($variant['path']);

        $variants = $file->variants();
        unset($variants[$name]);

        return $file->withVariants($variants, false);
    }

    /**
     * Gets the storage abstraction to use
     *
     * @param string $storage Storage name to use
     * @return \League\Flysystem\AdapterInterface
     */
    public function getStorage(string $storage): AdapterInterface
    {
        return $this->storageService->adapter($storage);
    }
}
