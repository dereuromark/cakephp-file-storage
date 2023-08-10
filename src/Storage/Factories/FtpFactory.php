<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use League\Flysystem\Adapter\Ftp;
use League\Flysystem\AdapterInterface;

/**
 * FtpFactory
 */
class FtpFactory extends AbstractFactory
{
    protected string $alias = 'ftp';

    protected ?string $package = 'league/flysystem';

    protected string $className = Ftp::class;

    protected array $defaults = [
        'host' => '',
        'username' => '',
        'password' => '',
        // Optional settings
        'port' => 21,
        'root' => '/',
        'passive' => true,
        'ssl' => true,
        'timeout' => 30,
        'ignorePassiveAddress' => false,
    ];

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        if (!defined('FTP_BINARY')) {
            define('FTP_BINARY', 'ftp.exe');
        }

        $config += $this->defaults;

        return new Ftp($config);
    }
}
