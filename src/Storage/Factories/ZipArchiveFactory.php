<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\AdapterInterface;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;

/**
 * ZipArchiveFactory
 */
class ZipArchiveFactory extends AbstractFactory
{
    protected string $alias = 'zip';

    protected ?string $package = 'league/flysystem-ziparchive';

    protected string $className = ZipArchiveAdapter::class;

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        $defaults = [
            'location' => null,
            'archive' => null,
            'prefix' => null,
        ];
        $config += $defaults;

        return new ZipArchiveAdapter($config['location'], $config['archive'], $config['prefix']);
    }
}
