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

use Intervention\Image\Image;
use InvalidArgumentException;
use FileStorage\Storage\Processor\Image\Exception\UnsupportedOperationException;

/**
 * Operations
 */
class Operations
{
    /**
     * @var \Intervention\Image\Image
     */
    protected Image $image;

    /**
     * @param \Intervention\Image\Image $image Image
     */
    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * @param string $name Name
     * @param array<string, mixed> $arguments Arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        throw UnsupportedOperationException::withName($name);
    }

    /**
     * Crops the image
     *
     * @link http://image.intervention.io/api/fit
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function fit(array $arguments): void
    {
        if (!isset($arguments['width'])) {
            throw new InvalidArgumentException('Missing width');
        }

        $preventUpscale = $arguments['preventUpscale'] ?? false;
        $height = $arguments['height'] ?? null;

        $this->image->fit(
            (int)$arguments['width'],
            (int)$height,
            static function ($constraint) use ($preventUpscale) {
                if ($preventUpscale) {
                    $constraint->upsize();
                }
            }
        );
    }

    /**
     * Crops the image
     *
     * @link http://image.intervention.io/api/crop
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function crop(array $arguments): void
    {
        if (!isset($arguments['height'], $arguments['width'])) {
            throw new InvalidArgumentException('Missing width or height');
        }

        $arguments = array_merge(['x' => null, 'y' => null], $arguments);
        $height = $arguments['height'] ? (int)$arguments['height'] : null;
        $width = $arguments['width'] ? (int)$arguments['width'] : null;
        $x = $arguments['x'] ? (int)$arguments['x'] : null;
        $y = $arguments['y'] ? (int)$arguments['y'] : null;

        $this->image->crop($width, $height, $x, $y);
    }

    /**
     * Flips the image horizontal
     *
     * @link http://image.intervention.io/api/flip
     * @return void
     */
    public function flipHorizontal(): void
    {
        $this->flip(['direction' => 'h']);
    }

    /**
     * Flips the image vertical
     *
     * @link http://image.intervention.io/api/flip
     * @return void
     */
    public function flipVertical(): void
    {
        $this->flip(['direction' => 'v']);
    }

    /**
     * Flips the image
     *
     * @link http://image.intervention.io/api/flip
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function flip(array $arguments): void
    {
        if (!isset($arguments['direction'])) {
            throw new InvalidArgumentException('Direction missing');
        }

        if ($arguments['direction'] !== 'v' && $arguments['direction'] !== 'h') {
            throw new InvalidArgumentException(
                'Invalid argument, you must provide h or v'
            );
        }

        $this->image->flip($arguments['direction']);
    }

    /**
     * Resizes the image
     *
     * @link http://image.intervention.io/api/resize
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function resize(array $arguments): void
    {
        if (!isset($arguments['height'], $arguments['width'])) {
            throw new InvalidArgumentException(
                'Missing height or width'
            );
        }

        $aspectRatio = $arguments['aspectRatio'] ?? true;
        $preventUpscale = $arguments['preventUpscale'] ?? false;

        $this->image->resize(
            $arguments['width'],
            $arguments['height'],
            static function ($constraint) use ($aspectRatio, $preventUpscale) {
                if ($aspectRatio) {
                    $constraint->aspectRatio();
                }
                if ($preventUpscale) {
                    $constraint->upsize();
                }
            }
        );
    }

    /**
     * @link http://image.intervention.io/api/widen
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function widen(array $arguments): void
    {
        if (!isset($arguments['width'])) {
            throw new InvalidArgumentException(
                'Missing width'
            );
        }

        $preventUpscale = $arguments['preventUpscale'] ?? false;

        $this->image->widen((int)$arguments['width'], function ($constraint) use ($preventUpscale) {
            if ($preventUpscale) {
                $constraint->upsize();
            }
        });
    }

    /**
     * @link http://image.intervention.io/api/heighten
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function heighten(array $arguments): void
    {
        if (!isset($arguments['height'])) {
            throw new InvalidArgumentException(
                'Missing height'
            );
        }

        $preventUpscale = $arguments['preventUpscale'] ?? false;

        $this->image->heighten((int)$arguments['height'], function ($constraint) use ($preventUpscale) {
            if ($preventUpscale) {
                $constraint->upsize();
            }
        });
    }

    /**
     * @link http://image.intervention.io/api/rotate
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function rotate(array $arguments): void
    {
        if (!isset($arguments['angle'])) {
            throw new InvalidArgumentException(
                'Missing angle'
            );
        }

        $this->image->rotate((int)$arguments['angle']);
    }

    /**
     * @link http://image.intervention.io/api/rotate
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function sharpen(array $arguments): void
    {
        if (!isset($arguments['amount'])) {
            throw new InvalidArgumentException(
                'Missing amount'
            );
        }

        $this->image->sharpen((int)$arguments['amount']);
    }

    /**
     * Allows the declaration of a callable that gets the image manager instance
     * and the arguments passed to it.
     *
     * @param array<string, mixed> $arguments Arguments
     * @return void
     */
    public function callback(array $arguments): void
    {
        if (!isset($arguments['callback'])) {
            throw new InvalidArgumentException(
                'Missing callback argument'
            );
        }

        if (!is_callable($arguments['callback'])) {
            throw new InvalidArgumentException(
                'Provided value for callback is not a callable'
            );
        }

        $arguments['callable']($this->image, $arguments);
    }
}
