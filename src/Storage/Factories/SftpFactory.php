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
        'directoryPerm' => 0755
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
