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

namespace FileStorage\Storage;

use Psr\Http\Message\UploadedFileInterface;

/**
 * File Factory Interface
 */
interface FileFactoryInterface
{
    /**
     * Create a file storage object from the PSR interface
     *
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile PSR Uploaded File
     * @param string $storage Storage to use
     * @return \FileStorage\Storage\FileInterface
     */
    public static function fromUploadedFile(
        UploadedFileInterface $uploadedFile,
        string $storage
    ): FileInterface;

    /**
     * From local disk
     *
     * @param string $path Path to local file
     * @param string $storage Storage
     * @return \FileStorage\Storage\FileInterface
     */
    public static function fromDisk(string $path, string $storage): FileInterface;
}
