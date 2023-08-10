<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor\Image;

use ArrayIterator;
use FileStorage\Storage\Processor\Exception\VariantExistsException;
use FileStorage\Storage\Processor\Image\Exception\UnsupportedOperationException;
use Iterator;
use ReflectionClass;

/**
 * Conversion Collection
 */
class ImageVariantCollection implements ImageVariantCollectionInterface
{
    /**
     * @var array<string, \FileStorage\Storage\Processor\Image\ImageVariant>
     */
    protected array $variants = [];

    /**
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Workaround for php 8 because call_user_func_array will now behave this way:
     * args keys will now be interpreted as parameter names, instead of being silently ignored.
     *
     * @param object $object
     * @param string $method
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    protected static function filterArgs(object $object, string $method, array $args): array
    {
        $filteredArgs = [];
        $variantReflection = new ReflectionClass($object);
        $methodReflection = $variantReflection->getMethod($method);
        $reflectionParameters = $methodReflection->getParameters();

        foreach ($reflectionParameters as $parameter) {
            if (isset($args[$parameter->getName()])) {
                $filteredArgs[$parameter->getName()] = $args[$parameter->getName()];
            }
        }

        return $filteredArgs;
    }

    /**
     * @param array<string, array<string, mixed>> $variants Variant array structure
     *
     * @return self
     */
    public static function fromArray(array $variants)
    {
        $that = new self();

        foreach ($variants as $name => $data) {
            $variant = ImageVariant::create($name);
            if (isset($data['optimize']) && $data['optimize'] === true) {
                $variant = $variant->optimize();
            }

            if (!empty($data['path']) && is_string($data['path'])) {
                $variant = $variant->withPath($data['path']);
            }

            foreach ($data['operations'] as $method => $args) {
                if (!method_exists($variant, $method)) {
                    UnsupportedOperationException::withName($method);
                }

                /** @var array<mixed> $parameters */
                $parameters = self::filterArgs($variant, $method, $args);
                $variant = call_user_func_array(
                    [$variant, $method],
                    $parameters,
                );
            }

            $that->add($variant);
        }

        return $that;
    }

    /**
     * @param string $name Name
     *
     * @return \FileStorage\Storage\Processor\Image\ImageVariant
     */
    public function addNew(string $name)
    {
        $this->add(ImageVariant::create($name));

        return $this->get($name);
    }

    /**
     * Gets a manipulation from the collection
     *
     * @param string $name
     *
     * @return \FileStorage\Storage\Processor\Image\ImageVariant
     */
    public function get(string $name): ImageVariant
    {
        return $this->variants[$name];
    }

    /**
     * @param \FileStorage\Storage\Processor\Image\ImageVariant $variant Variant
     *
     * @throws \FileStorage\Storage\Processor\Exception\VariantExistsException
     *
     * @return void
     */
    public function add(ImageVariant $variant): void
    {
        if ($this->has($variant->name())) {
            throw VariantExistsException::withName($variant->name());
        }

        $this->variants[$variant->name()] = $variant;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->variants[$name]);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function remove(string $name): void
    {
        unset($this->variants[$name]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->variants);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->variants as $variant) {
            $array[$variant->name()] = $variant->toArray();
        }

        return $array;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->variants);
    }
}
