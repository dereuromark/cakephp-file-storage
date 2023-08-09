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
use League\Flysystem\Config;
use League\Flysystem\Memory\MemoryAdapter;

/**
 * Memory
 */
class MemoryFactory extends AbstractFactory
{
    protected string $alias = 'memory';
    protected ?string $package = 'league/flysystem-memory';
    protected string $className = MemoryAdapter::class;

    /**
     * @inheritDoc
     */
    public function build(array $config): AdapterInterface
    {
        return new MemoryAdapter();
    }
}
