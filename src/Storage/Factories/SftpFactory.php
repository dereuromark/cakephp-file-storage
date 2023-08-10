<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Sftp\SftpAdapter;

/**
 * SftpFactory
 */
class SftpFactory extends AbstractFactory
{
    protected string $alias = 'sftp';

    protected ?string $package = 'league/flysystem-sftp';

    protected string $className = SftpAdapter::class;

    protected array $defaults = [
        'host' => '',
        'port' => 22,
        'username' => '',
        'password' => '',
        'privateKey' => '',
        'passphrase' => '',
        'root' => '/',
        'timeout' => 10,
        'directoryPerm' => 0755,
    ];

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        $config += $this->defaults;

        return new SftpAdapter($config);
    }
}
