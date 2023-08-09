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
        'authToken' => ''
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
