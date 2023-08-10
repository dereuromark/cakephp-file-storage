<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\AdapterInterface;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

/**
 * DropboxFactory
 */
class DropboxFactory extends AbstractFactory
{
    protected string $alias = 'null';

    protected ?string $package = 'spatie/flysystem-dropbox';

    protected string $className = DropboxAdapter::class;

    protected array $defaults = [
        'authToken' => '',
    ];

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        $config += $this->defaults;
        $client = new Client($config['authToken']);

        return new DropboxAdapter($client);
    }
}
