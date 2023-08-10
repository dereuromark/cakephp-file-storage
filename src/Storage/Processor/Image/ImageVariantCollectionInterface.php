<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor\Image;

use Countable;
use IteratorAggregate;
use JsonSerializable;

interface ImageVariantCollectionInterface extends JsonSerializable, IteratorAggregate, Countable
{
    /**
     * Gets a manipulation from the collection
     *
     * @param string $name
     *
     * @return \FileStorage\Storage\Processor\Image\ImageVariant
     */
    public function get(string $name): ImageVariant;

    /**
     * @param \FileStorage\Storage\Processor\Image\ImageVariant $variant Variant
     *
     * @return void
     */
    public function add(ImageVariant $variant): void;

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool;
}
