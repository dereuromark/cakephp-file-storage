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

use JsonSerializable;
use FileStorage\Storage\PathBuilder\PathBuilderInterface;
use FileStorage\Storage\UrlBuilder\UrlBuilderInterface;

/**
 * File Interface
 */
interface FileInterface extends JsonSerializable
{
    /**
     * The uuid of the file
     *
     * @return string
     */
    public function uuid(): string;

    /**
     * Filename
     *
     * @return string
     */
    public function filename(): string;

    /**
     * Filesize
     *
     * @return int
     */
    public function filesize(): int;

    /**
     * Mime Type
     *
     * @return null|string
     */
    public function mimeType(): ?string;

    /**
     * Model name
     *
     * @return null|string
     */
    public function model(): ?string;

    /**
     * Model ID
     *
     * @return string
     */
    public function modelId(): ?string;

    /**
     * Gets the metadata array
     *
     * @return array<mixed, mixed>
     */
    public function metadata(): array;

    /**
     * Resource to store
     *
     * @return resource|null
     */
    public function resource();

    /**
     * Collection name
     *
     * @return null|string
     */
    public function collection(): ?string;

    /**
     * Adds the file to a collection
     *
     * @param string $collection Collection
     * @return $this
     */
    public function addToCollection(string $collection): FileInterface;

    /**
     * Get a variant
     *
     * @param string $name
     * @return array
     */
    public function variant(string $name): array;

    /**
     * Array data structure of the variants
     *
     * @return array
     */
    public function variants(): array;

    /**
     * Checks if the file has a specific variant
     *
     * @param string $name
     * @return bool
     */
    public function hasVariant(string $name): bool;

    /**
     * Checks if the file has any variants
     *
     * @return bool
     */
    public function hasVariants(): bool;

    /**
     * @param string $name Name
     * @param array $data Data
     * @return \FileStorage\Storage\FileInterface
     */
    public function withVariant(string $name, array $data): FileInterface;

    /**
     * @param array $variants Variants
     * @param bool $merge Merge variants, default is false
     * @return \FileStorage\Storage\FileInterface
     */
    public function withVariants(array $variants, bool $merge = true): FileInterface;

    /**
     * Gets the paths for all variants
     *
     * @return array
     */
    public function variantPaths(): array;

    /**
     * Returns an array of the file data
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Adds (replaces) the existing metadata
     *
     * @param array<mixed, mixed> $metadata Metadata
     * @param bool $overwrite
     * @return \FileStorage\Storage\FileInterface
     */
    public function withMetadata(array $metadata, bool $overwrite = false): FileInterface;

    /**
     * Removes all metadata
     *
     * @return \FileStorage\Storage\FileInterface
     */
    public function withoutMetadata(): FileInterface;

    /**
     * Adds a single key and value to the metadata array
     *
     * @param string $key Key
     * @param mixed $data
     * @return \FileStorage\Storage\FileInterface
     */
    public function withMetadataByKey(string $key, $data): FileInterface;

    /**
     * Removes a key from the metadata array
     * @param string $name Name
     * @return \FileStorage\Storage\FileInterface
     */
    public function withoutMetadataByKey(string $name): FileInterface;

    /**
     * Stream resource of the file to be stored
     *
     * @param resource  $resource
     * @return \FileStorage\Storage\FileInterface
     */
    public function withResource($resource): FileInterface;

    /**
     * Same as withResource() but takes a file path
     *
     * @param string $file File
     * @return \FileStorage\Storage\FileInterface
     */
    public function withFile(string $file): FileInterface;

    /**
     * Returns the path for the file in the storage system
     *
     * This is probably most of the time a *relative* and not an absolute path
     * to some root or container depending on the storage backend.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Sets the path, immutable
     *
     * @param string $path Path to the file
     * @return \FileStorage\Storage\FileInterface
     */
    public function withPath(string $path): FileInterface;

    /**
     * Builds the path for this file
     *
     * Keep in mind that the path will depend on the path builder configuration!
     * The resulting path depends on the builder!
     *
     * @param \FileStorage\Storage\PathBuilder\PathBuilderInterface $pathBuilder Path Builder
     * @return $this
     */
    public function buildPath(PathBuilderInterface $pathBuilder): FileInterface;

    /**
     * Builds the URL for this file
     *
     * Keep in mind that the URL will depend on the URL builder configuration!
     * The resulting URL depends on the builder!
     *
     * @param \FileStorage\Storage\UrlBuilder\UrlBuilderInterface $urlBuilder URL Builder
     * @return \FileStorage\Storage\FileInterface
     */
    public function buildUrl(UrlBuilderInterface $urlBuilder): FileInterface;

    /**
     * Gets the URL for the file
     *
     * @return string
     */
    public function url(): string;

    /**
     * Sets a URL
     *
     * @param string $url URL
     * @return \FileStorage\Storage\FileInterface
     */
    public function withUrl(string $url): FileInterface;

    /**
     * Storage name
     *
     * @return string
     */
    public function storage(): string;

    /**
     * Returns the filenames extension
     *
     * @return string|null
     */
    public function extension(): ?string;

    /**
     * UUID of the file
     *
     * @param string $uuid UUID string
     * @return \FileStorage\Storage\FileInterface
     */
    public function withUuid(string $uuid): FileInterface;

    /**
     * Filename
     *
     * Be aware that the filename doesn't have to match the name of the actual
     * file in the storage backend!
     *
     * @param string $filename Filename
     * @return \FileStorage\Storage\FileInterface
     */
    public function withFilename(string $filename): FileInterface;

    /**
     * Assign a model and model id to a file
     *
     * @param string $model Model
     * @param string|int $modelId Model ID, UUID string or integer
     * @return \FileStorage\Storage\FileInterface
     */
    public function belongsToModel(string $model, $modelId): FileInterface;
}
