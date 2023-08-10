<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use FileStorage\Storage\Exception\PackageRequiredException;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;

/**
 * AbstractFactory
 */
abstract class AbstractFactory implements FactoryInterface
{
    /**
     * @var string
     */
    protected string $alias = 'local';

    /**
     * @var string|null
     */
    protected ?string $package = 'league/flysystem';

    /**
     * @var string
     */
    protected string $className = Local::class;

    /**
     * @return string
     */
    public function className(): string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function alias(): string
    {
        return $this->alias;
    }

    /**
     * @throws \FileStorage\Storage\Exception\PackageRequiredException
     *
     * @return void
     */
    public function availabilityCheck(): void
    {
        if (!class_exists($this->className)) {
            throw PackageRequiredException::fromAdapterAndPackageNames(
                $this->alias,
                $this->package,
            );
        }
    }

    /**
     * @inheritDoc
     */
    abstract public function build(array $config): AdapterInterface;
}
