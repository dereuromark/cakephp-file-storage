<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor;

/**
 * Manipulation
 */
class Variant implements VariantInterface
{
    protected string $name = '';

    protected array $operations = [];

    protected string $path = '';

    protected string $url = '';

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'operations' => $this->operations,
            'path' => $this->path,
            'url' => $this->url,
        ];
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return bool
     */
    public function hasOperations(): bool
    {
        return count($this->operations) > 0;
    }

    /**
     * @param string $url Path
     *
     * @return self
     */
    public function withUrl(string $url): self
    {
        $that = clone $this;
        $that->url = $url;

        return $that;
    }

    /**
     * @param string $path Path
     *
     * @return self
     */
    public function withPath(string $path): self
    {
        $that = clone $this;
        $that->path = $path;

        return $that;
    }
}
