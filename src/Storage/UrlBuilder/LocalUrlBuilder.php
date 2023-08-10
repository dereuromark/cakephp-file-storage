<?php

declare(strict_types = 1);

namespace FileStorage\Storage\UrlBuilder;

use FileStorage\Storage\FileInterface;

/**
 * Local URL Builder
 *
 * This URL builder assumes that your stored files are available using the
 * path under which they were stored and just prefixes their path with an URL
 * schema and host and a base path.
 *
 * This is a pretty basic URL builder based on a simple setup and assumption.
 * If you need a more complex URL building logic that involves your custom
 * router class or something like that, please implement your own URL builder
 * using the interface.
 */
class LocalUrlBuilder implements UrlBuilderInterface
{
    /**
     * @var string
     */
    protected string $basePath = '/';

    /**
     * @param string $basePath Base Path
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @inheritDoc
     */
    public function url(FileInterface $file): string
    {
        return str_replace('\\', '/', $this->buildBaseUrl() . $file->path());
    }

   /**
    * @inheritDoc
    */
    public function urlForVariant(FileInterface $file, string $variant): string
    {
        if (!isset($file->variants()[$variant])) {
            return '';
        }

        return $this->buildBaseUrl() . $file->variants()[$variant]['url'];
    }

    /**
     * @return string
     */
    protected function buildBaseUrl(): string
    {
        return $this->basePath;
    }
}
