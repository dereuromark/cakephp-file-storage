<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\AdapterInterface;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Sabre\DAV\Client;

/**
 * WebdavFactory
 */
class WebDAVFactory extends AbstractFactory
{
    protected string $alias = 'webdav';

    protected ?string $package = 'league/flysystem-webdav';

    protected string $className = WebDAVAdapter::class;

    protected array $defaults = [
        'baseUri' => '',
        'userName' => '',
        'password' => '',
        'proxy' => '',
    ];

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        return new WebDAVAdapter(new Client($config));
    }
}
