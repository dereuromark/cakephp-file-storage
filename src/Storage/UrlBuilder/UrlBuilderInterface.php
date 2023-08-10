<?php

declare(strict_types = 1);

namespace FileStorage\Storage\UrlBuilder;

use FileStorage\Storage\FileInterface;

/**
 * UrlBuilderInterface
 */
interface UrlBuilderInterface
{
   /**
    * @param \FileStorage\Storage\FileInterface $file File
    *
    * @return string
    */
    public function url(FileInterface $file): string;

   /**
    * @param \FileStorage\Storage\FileInterface $file File
    * @param string $variant Version
    *
    * @return string
    */
    public function urlForVariant(FileInterface $file, string $variant): string;
}
