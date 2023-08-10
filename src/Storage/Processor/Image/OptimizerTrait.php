<?php

declare(strict_types = 1);

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
        if ($this->optimizerChain) {
            return $this->optimizerChain;
        }

        $this->optimizerChain = OptimizerChainFactory::create();

        return $this->optimizerChain;
    }
}
