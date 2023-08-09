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
use FileStorage\Storage\Exception\PackageRequiredException;
use FileStorage\Storage\InstantiateTrait;

/**
 * AbstractFactory
 */
abstract class AbstractFactory implements FactoryInterface
{
    /**
     * @var string
     */
    protected string $alias = 'local';

    /**
     * @var string|null
     */
    protected ?string $package = 'league/flysystem';

    /**
     * @var string
     */
    protected string $className = Local::class;

    /**
     * @return string
     */
    public function className(): string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function alias(): string
    {
        return $this->alias;
    }

    /**
     * @return void
     */
    public function availabilityCheck(): void
    {
        if (!class_exists($this->className)) {
            throw PackageRequiredException::fromAdapterAndPackageNames(
                $this->alias,
                $this->package
            );
        }
    }

    /**
     * @inheritDoc
     */
    abstract public function build(array $config): AdapterInterface;
}
