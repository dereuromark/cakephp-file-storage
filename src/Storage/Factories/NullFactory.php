<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\Adapter\NullAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

/**
 * NullFactory
 */
class NullFactory extends AbstractFactory
{
    protected string $alias = 'null';

    protected string $className = AwsS3Adapter::class;

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        return new NullAdapter();
    }
}
