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

namespace FileStorage\Storage\Processor\Image;

use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\OptimizerChainFactory;

/**
 * Optimizer Trait
 */
trait OptimizerTrait
{
    /**
     * Optimizer Chain
     *
     * @var \Spatie\ImageOptimizer\OptimizerChain
     */
    protected OptimizerChain $optimizerChain;

    /**
     * @return \Spatie\ImageOptimizer\OptimizerChain
     */
    public function optimizer(): OptimizerChain
    {
        if (!empty($this->optimizerChain)) {
            return $this->optimizerChain;
        }

        $this->optimizerChain = OptimizerChainFactory::create();

        return $this->optimizerChain;
    }
}
