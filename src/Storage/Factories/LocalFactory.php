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

use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;

/**
 * LocalFactory
 */
class LocalFactory extends AbstractFactory
{
    protected string $alias = 'local';
    protected ?string $package = 'league/flysystem';
    protected string $className = Local::class;

    public function build(array $config): AdapterInterface
    {
        $this->availabilityCheck();

        $config += ['root' => '/'];

        return new Local($config['root']);
    }
}
