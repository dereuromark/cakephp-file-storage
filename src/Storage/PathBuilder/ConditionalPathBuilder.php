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

namespace FileStorage\Storage\PathBuilder;

use FileStorage\Storage\FileInterface;

/**
 * Add callbacks and path builders to check on the file which of the builders
 * should be used to build the path.
 *
 * This allows you to use different instances with a different configuration or
 * implementation of path builders for files of different types or in different
 * collections or models.
 */
class ConditionalPathBuilder implements PathBuilderInterface
{
    /**
     * @var array
     */
    protected array $pathBuilders = [];

    /**
     * @var \FileStorage\Storage\PathBuilder\PathBuilderInterface
     */
    protected PathBuilderInterface $defaultPathBuilder;

    /**
     * @param \FileStorage\Storage\PathBuilder\PathBuilderInterface $pathBuilder Default Path Builder
     */
    public function __construct(PathBuilderInterface $pathBuilder)
    {
        $this->defaultPathBuilder = $pathBuilder;
    }

    /**
     * @param \FileStorage\Storage\PathBuilder\PathBuilderInterface $pathBuilder Path Builder
     * @param callable $conditionCheck Condition check
     * @return $this
     */
    public function addPathBuilder(PathBuilderInterface $pathBuilder, callable $conditionCheck): self
    {
        $this->pathBuilders[] = [
            'callable' => $conditionCheck,
            'pathBuilder' => $pathBuilder
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function path(FileInterface $file, array $options = []): string
    {
        foreach ($this->pathBuilders as $builder) {
            if ($builder['callable']($file)) {
                return $builder['pathBuilder']->path($file, $options);
            }
        }

        return $this->defaultPathBuilder->path($file, $options);
    }

    /**
     * @inheritDoc
     */
    public function pathForVariant(FileInterface $file, string $name, array $options = []): string
    {
        foreach ($this->pathBuilders as $builder) {
            if ($builder['callable']($file)) {
                return $builder['pathBuilder']->pathForVariant($file, $name, $options);
            }
        }

        return $this->defaultPathBuilder->pathForVariant($file, $name, $options);
    }
}
