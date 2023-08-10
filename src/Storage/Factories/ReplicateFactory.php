<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use FileStorage\Storage\Exception\PackageRequiredException;
use FileStorage\Storage\Factories\Exception\FactoryException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Replicate\ReplicateAdapter;

/**
 * ReplicateFactory
 */
class ReplicateFactory extends AbstractFactory
{
    protected string $alias = 'replicate';

    protected ?string $package = 'league/flysystem-replicate-adapter';

    protected string $className = ReplicateAdapter::class;

    /**
     * @inheritDoc
     *
     * @throws \FileStorage\Storage\Exception\PackageRequiredException
     * @throws \FileStorage\Storage\Factories\Exception\FactoryException
     */
    public function build(array $config): AdapterInterface
    {
        if (!class_exists(ReplicateAdapter::class)) {
            throw PackageRequiredException::fromAdapterAndPackageNames(
                'replicate',
                'league/flysystem-replicate-adapter',
            );
        }

        if (!isset($config['source']) || !isset($config['target'])) {
            throw new FactoryException(
                'You must configure `source` and `target`',
            );
        }

        return new ReplicateAdapter(
            $config['source'],
            $config['target'],
        );
    }
}
