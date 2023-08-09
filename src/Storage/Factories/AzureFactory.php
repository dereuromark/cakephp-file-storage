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
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use FileStorage\Storage\Factories\Exception\FactoryConfigException;
use FileStorage\Storage\Factories\Exception\FactoryException;

/**
 * Azure Factory
 *
 * Be aware that this adapter seems to have some problems!
 *
 * @link https://github.com/thephpleague/flysystem-azure/issues/22
 * @link https://github.com/thephpleague/flysystem-azure/issues/15
 */
class AzureFactory extends AbstractFactory
{
    protected string $alias = 'azure';
    protected ?string $package = 'league/flysystem-azure-blob-storage';
    protected string $className = AzureBlobStorageAdapter::class;

    protected $endpoint = 'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s';

    /**
     * @inheritDoc
     */
    public function build($config): AdapterInterface
    {
        $this->availabilityCheck();
        $this->checkConfig($config);

        $endpoint = sprintf(
            $this->endpoint,
            base64_encode($config['accountName']),
            base64_encode($config['apiKey'])
        );

        $client = BlobRestProxy::createBlobService($endpoint);

        return new AzureBlobStorageAdapter($client, $config['accountName']);
    }

    protected function checkConfig(array $config): void
    {
        if (empty($config['accountName'])) {
            throw FactoryConfigException::withMissingKey('apiKey', $this);
        }

        if (empty($config['apiKey'])) {
            throw FactoryConfigException::withMissingKey('apiKey', $this);
        }
    }
}
