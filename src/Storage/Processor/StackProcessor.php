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

namespace FileStorage\Storage\Processor;

use FileStorage\Storage\FileInterface;

/**
 * The stack processor takes a list of other processors and processes them in
 * the order they were added to the stack.
 */
class StackProcessor implements ProcessorInterface
{
    protected array $processors = [];

    /**
     * @param \FileStorage\Storage\Processor\ProcessorInterface[] $processors
     */
    public function __construct(array $processors)
    {
        foreach ($processors as $processor) {
            $this->add($processor);
        }
    }

    /**
     * @param \FileStorage\Storage\Processor\ProcessorInterface $processor
     */
    public function add(ProcessorInterface $processor): void
    {
        $this->processors[] = $processor;
    }

    /**
     * @inheritdoc
     */
    public function process(FileInterface $file): FileInterface
    {
        foreach ($this->processors as $processor) {
            $file = $processor->process($file);
        }

        return $file;
    }
}
