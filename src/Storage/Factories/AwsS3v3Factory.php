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
            'version' => '2006-03-01'
        ]
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
                $config['client']
            ),
            $config['bucket'],
            $config['prefix'],
            $config
        );
    }
}
