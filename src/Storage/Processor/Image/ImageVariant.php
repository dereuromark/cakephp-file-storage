<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Processor\Image;

use FileStorage\Storage\Processor\Variant;
use InvalidArgumentException;

/**
 * Image Manipulation
 */
class ImageVariant extends Variant
{
    protected string $name;

    /**
     * @var array<int, string>
     */
    protected array $operations;

    protected string $path = '';

    protected bool $optimize = false;

    protected string $url = '';

    /**
     * @var string
     */
    public const FLIP_HORIZONTAL = 'h';

    /**
     * @var string
     */
    public const FLIP_VERTICAL = 'v';

    /**
     * @param string $name Name
     *
     * @return self
     */
    public static function create(string $name): self
    {
        $self = new self();
        $self->name = $name;

        return $self;
    }

    /**
     * Try to apply image optimizations if available on the system
     *
     * @return $this
     */
    public function optimize()
    {
        $this->optimize = true;

        return $this;
    }

    /**
     * @param int $height Width
     * @param int|null $width Height
     * @param int|null $x X
     * @param int|null $y Y
     *
     * @return $this
     */
    public function crop(int $height, ?int $width = null, ?int $x = null, ?int $y = null)
    {
        $this->operations['crop'] = [
            'width' => $width,
            'height' => $height,
            'x' => $x,
            'y' => $y,
        ];

        return $this;
    }

    /**
     * @param int $amount Angle
     *
     * @return $this
     */
    public function sharpen(int $amount)
    {
        $this->operations['sharpen'] = [
            'amount' => $amount,
        ];

        return $this;
    }

    /**
     * @param int $angle Angle
     *
     * @return $this
     */
    public function rotate(int $angle)
    {
        $this->operations['rotate'] = [
            'angle' => $angle,
        ];

        return $this;
    }

    /**
     * @param int $height Height
     * @param bool $preventUpscale Prevent Upscaling
     *
     * @return $this
     */
    public function heighten(int $height, bool $preventUpscale = false)
    {
        $this->operations['heighten'] = [
            'height' => $height,
            'preventUpscale' => $preventUpscale,
        ];

        return $this;
    }

    /**
     * @param int $width Width
     * @param bool $preventUpscale Prevent Upscaling
     *
     * @return $this
     */
    public function widen(int $width, bool $preventUpscale = false)
    {
        $this->operations['widen'] = [
            'width' => $width,
            'preventUpscale' => $preventUpscale,
        ];

        return $this;
    }

    /**
     * @param int $width Width
     * @param int $height Height
     * @param bool $aspectRatio Keeps the aspect ratio
     * @param bool $preventUpscale Prevents upscaling
     *
     * @return $this
     */
    public function resize(int $width, int $height, bool $aspectRatio = true, bool $preventUpscale = false)
    {
        $this->operations['resize'] = [
            'width' => $width,
            'height' => $height,
            'aspectRatio' => $aspectRatio,
            'preventUpscale' => $preventUpscale,
        ];

        return $this;
    }

    /**
     * Flips the image horizontal
     *
     * @return $this
     */
    public function flipHorizontal()
    {
        $this->operations['flipHorizontal'] = [
            'direction' => self::FLIP_HORIZONTAL,
        ];

        return $this;
    }

    /**
     * Flips the image vertical
     *
     * @return $this
     */
    public function flipVertical()
    {
        $this->operations['flipVertical'] = [
            'direction' => self::FLIP_VERTICAL,
        ];

        return $this;
    }

    /**
     * Flips the image
     *
     * @param string $direction Direction, h or v
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function flip(string $direction)
    {
        if ($direction !== 'h' && $direction !== 'v') {
            throw new InvalidArgumentException(sprintf(
                '`%s` is invalid, provide `h` or `v`',
                $direction,
            ));
        }

        $this->operations['flip'] = [
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Allows the declaration of a callable that gets the image manager instance
     * and the arguments passed to it.
     *
     * @param callable $callback callback
     *
     * @return $this
     */
    public function callback(callable $callback)
    {
        $this->operations['callback'] = [
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * @link http://image.intervention.io/api/fit
     *
     * @param int $width Width
     * @param int|null $height Height
     * @param callable|null $callback Callback
     * @param bool $preventUpscale Prevent Upscaling
     * @param string $position Position
     *
     * @return $this
     */
    public function fit(
        int $width,
        ?int $height = null,
        ?callable $callback = null,
        bool $preventUpscale = false,
        string $position = 'center'
    ) {
        $this->operations['fit'] = [
            'width' => $width,
            'height' => $height,
            'callback' => $callback,
            'preventUpscale' => $preventUpscale,
            'position' => $position,
        ];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operations' => $this->operations,
            'path' => $this->path,
            'url' => $this->url,
            'optimize' => $this->optimize,
        ];
    }
}
