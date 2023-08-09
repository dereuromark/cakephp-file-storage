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

namespace FileStorage\Storage\PathBuilder;

use FileStorage\Storage\FileInterface;

/**
 * PathBuilderInterface
 */
interface PathBuilderInterface
{
    /**
     * Builds the path under which the data gets stored in the storage adapter.
     *
     * @param \FileStorage\Storage\FileInterface $file
     * @param array $options
     * @return string
     */
    public function path(FileInterface $file, array $options = []): string;

    /**
     * Builds the path for a manipulated version of the file.
     *
     * This can be thumbnail of an image or a few different versions of a video.
     *
     * @param \FileStorage\Storage\FileInterface $file
     * @param string $name Name of the operation
     * @param array $options
     * @return string
     */
    public function pathForVariant(FileInterface $file, string $name, array $options = []): string;
}
