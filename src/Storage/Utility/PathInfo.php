<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Utility;

/**
 * OO version of pathinfo()
 */
class PathInfo
{
    /**
     * @var string
     */
    protected $dirname;

    /**
     * @var string
     */
    protected $basename;

    /**
     * @var string|null
     */
    protected $extension;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @param string $path Path
     */
    private function __construct(string $path)
    {
        $info = pathinfo($path);

        $this->dirname = $info['dirname'];
        $this->basename = $info['basename'];
        $this->extension = empty($info['extension']) ? null : $info['extension'];
        $this->filename = $info['filename'];
    }

    /**
     * @return self
     */
    public static function for(string $path): self
    {
        return new self($path);
    }

    /**
     * @return string|null
     */
    public function dirname(): ?string
    {
        return $this->dirname;
    }

    /**
     * @return string
     */
    public function basename(): string
    {
        return $this->basename;
    }

    /**
     * @return string|null
     */
    public function extension(): ?string
    {
        return $this->extension;
    }

    /**
     * @return string
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * @return bool
     */
    public function hasExtension(): bool
    {
        return $this->extension !== null;
    }
}
