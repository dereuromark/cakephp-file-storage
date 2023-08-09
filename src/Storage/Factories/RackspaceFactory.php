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
use League\Flysystem\Rackspace\RackspaceAdapter;
use OpenCloud\Rackspace;

/**
 * RackspaceFactory
 */
class RackspaceFactory extends AbstractFactory
{
    protected string $alias = 'rackspace';
    protected ?string $package = 'league/flysystem-rackspace';
    protected string $className = RackspaceAdapter::class;
    protected array $defaults = [
        'identityEndpoint' => Rackspace::UK_IDENTITY_ENDPOINT,
        'username' => '',
        'apiKey' => '',
        'objectStoreService' => 'cloudFiles',
        'serviceRegion' => 'LON',
        'container' => 'flysystem',
        'serviceName' => 'cloudFiles'
    ];

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        $config += $this->defaults;

        $client = $this->buildClient($config);
        $store = $client->objectStoreService($config['serviceName'], $config['serviceRegion']);
        $container = $store->getContainer($config['container']);

        return new RackspaceAdapter($container);
    }

    /**
     * @param array $config Config
     * @return \OpenCloud\Rackspace
     */
    protected function buildClient(array $config): Rackspace
    {
        return new Rackspace($config['identityEndpoint'], array(
            'username' => $config['username'],
            'apiKey' => $config['apiKey'],
        ));
    }
}
