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
use League\Flysystem\WebDAV\WebDAVAdapter;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;

/**
 * ZipArchiveFactory
 */
class ZipArchiveFactory extends AbstractFactory
{
    protected string $alias = 'zip';
    protected ?string $package = 'league/flysystem-ziparchive';
    protected string $className = ZipArchiveAdapter::class;

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        $defaults = [
            'location' => null,
            'archive' => null,
            'prefix' => null,
        ];
        $config += $defaults;

        return new ZipArchiveAdapter($config['location'], $config['archive'], $config['prefix']);
    }
}
