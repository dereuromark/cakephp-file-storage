<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Factories;

use Aws\S3\S3Client;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

/**
 * AwsS3Factory
 */
class AwsS3v3Factory extends AbstractFactory
{
    protected string $alias = 's3';

    protected ?string $package = 'league/flysystem-aws-s3-v3';

    protected string $className = AwsS3Adapter::class;

    protected array $defaults = [
        'bucket' => null,
        'prefix' => '',
        'client' => [
            'region' => 'eu',
            'version' => '2006-03-01',
        ],
    ];

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        $this->availabilityCheck();
        $config += $this->defaults;

        return new AwsS3Adapter(
            S3Client::factory(
                $config['client'],
            ),
            $config['bucket'],
            $config['prefix'],
            $config,
        );
    }
}
