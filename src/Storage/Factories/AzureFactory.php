<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use FileStorage\Storage\Factories\Exception\FactoryConfigException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

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
            base64_encode($config['apiKey']),
        );

        $client = BlobRestProxy::createBlobService($endpoint);

        return new AzureBlobStorageAdapter($client, $config['accountName']);
    }

    /**
     * @throws \FileStorage\Storage\Factories\Exception\FactoryConfigException
     *
     * @return void
     */
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
