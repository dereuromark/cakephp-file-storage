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

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use FileStorage\Storage\FileInterface;
use FileStorage\Storage\Utility\FilenameSanitizer;
use FileStorage\Storage\Utility\FilenameSanitizerInterface;
use FileStorage\Storage\Utility\PathInfo;

/**
 * A path builder is an utility class that generates a path and filename for a
 * file storage entity. All the fields from the entity can bed used to create
 * a path and file name.
 */
class PathBuilder implements PathBuilderInterface
{
    /**
     * Default settings.
     *
     * @var array
     */
    protected array $defaultConfig = [
        'directorySeparator' => DIRECTORY_SEPARATOR,
        'randomPath' => 'sha1',
        'randomPathLevels' => 3,
        'sanitizeFilename' => true,
        'beautifyFilename' => false,
        'filenameSanitizer' => null,
        'pathTemplate' => '{model}{ds}{randomPath}{ds}{strippedId}{ds}{filename}.{extension}',
        'variantPathTemplate' => '{model}{ds}{randomPath}{ds}{strippedId}{ds}{filename}.{hashedVariant}.{extension}',
        'dateFormat' => [
            'year' => 'Y',
            'month' => 'm',
            'day' => 'd',
            'hour' => 'H',
            'minute' => 'i',
            'custom' => 'Y-m-d'
        ]
    ];

    /**
     * @var array
     */
    protected array $config = [];

    /**
     * @var \FileStorage\Storage\Utility\FilenameSanitizerInterface
     */
    protected FilenameSanitizerInterface $filenameSanitizer;

    /**
     * Constructor
     *
     * @param array $config Configuration options.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config + $this->defaultConfig;

        if (!$this->config['filenameSanitizer'] instanceof FilenameSanitizerInterface) {
            $this->filenameSanitizer = new FilenameSanitizer();
        }
    }

    /**
     * @param \FileStorage\Storage\Utility\FilenameSanitizerInterface $sanitizer
     * @return self
     */
    public function setFilenameSanitizer(FilenameSanitizerInterface $sanitizer): self
    {
        $this->filenameSanitizer = $sanitizer;

        return $this;
    }

    /**
     * @param string $template Template string
     * @return self
     */
    public function setPathTemplate(string $template): self
    {
        $this->config['pathTemplate'] = $template;

        return $this;
    }

    /**
     * @param string $template Template string
     * @return self
     */
    public function setVariantPathTemplate(string $template): self
    {
        $this->config['variantPathTemplate'] = $template;

        return $this;
    }

    /**
     * @param string $format Date format
     * @return self
     */
    public function setCustomDateFormat(string $format): self
    {
        $this->config['dateFormat']['custom'] = $format;

        return $this;
    }

    /**
     * Builds the path under which the data gets stored in the storage adapter.
     *
     * @param \FileStorage\Storage\FileInterface $file
     * @param array $options Options
     * @return string
     */
    public function path(FileInterface $file, array $options = []): string
    {
        return $this->buildPath($file, null, $options);
    }

    /**
     * @inheritDoc
     */
    public function pathForVariant(FileInterface $file, string $variant, array $options = []): string
    {
        return $this->buildPath($file, $variant, $options);
    }

    /**
     * @param \FileStorage\Storage\FileInterface $file
     * @param array $options Options
     * @return string
     */
    protected function filename(FileInterface $file, array $options = []): string
    {
        $config = $options + $this->config;

        $pathInfo = PathInfo::for($file->filename());
        $filename = $pathInfo->filename();

        if ($config['sanitizeFilename'] === true) {
            $filename = $this->filenameSanitizer->sanitize($pathInfo->filename());
        }

        if ($config['beautifyFilename'] === true) {
            $filename = $this->filenameSanitizer->beautify($pathInfo->filename());
        }

        return $filename;
    }

    /**
     * Creates a semi-random path based on a string.
     *
     * Makes it possible to overload this functionality.
     *
     * @param string $string Input string
     * @param int $level Depth of the path to generate.
     * @param string $method Hash method, crc32 or sha1.
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function randomPath($string, $level = 3, $method = 'sha1'): string
    {
        if ($method === 'sha1') {
            return $this->randomPathSha1($string, $level);
        }

        if (is_callable($method)) {
            return $method($string, $level);
        }

        throw new InvalidArgumentException(sprintf(
            'BasepathBuilder::randomPath() invalid hash `%s` method provided!',
            $method
        ));
    }

    /**
     * Creates a semi-random path based on a string.
     *
     * Makes it possible to overload this functionality.
     *
     * @param string $string Input string
     * @param int $level Depth of the path to generate.
     * @return string
     */
    protected function randomPathSha1(string $string, int $level): string
    {
        $result = sha1($string);
        $randomString = '';
        $counter = 0;
        for ($i = 1; $i <= $level; $i++) {
            $counter += 2;
            $randomString .= substr($result, $counter, 2) . DIRECTORY_SEPARATOR;
        }

        return substr($randomString, 0, -1);
    }

    /**
     * Override this methods if you want or need another object
     *
     * @return \DateTimeInterface
     */
    protected function getDateObject(): DateTimeInterface
    {
        return new DateTime();
    }

    /**
     * @inheritDoc
     */
    protected function buildPath(FileInterface $file, ?string $variant, array $options = []): string
    {
        $config = $options + $this->config;
        $ds = $this->config['directorySeparator'];
        $filename = $this->filename($file, $options);
        $hashedVariant = substr(hash('sha1', (string)$variant), 0, 6);
        $template = $variant ? $config['variantPathTemplate'] : $config['pathTemplate'];
        $dateTime = $this->getDateObject();
        $randomPathLevels = (int)$config['randomPathLevels'] ?: 3;

        $placeholders = [
            '{ds}' => $ds,
            '{model}' => $file->model(),
            '{collection}' => $file->collection(),
            '{id}' => $file->uuid(),
            '{randomPath}' => $this->randomPath($file->uuid(), $randomPathLevels),
            '{modelId}' => $file->modelId(),
            '{strippedId}' => str_replace('-', '', $file->uuid()),
            '{extension}' => $file->extension(),
            '{mimeType}' => $file->mimeType(),
            '{filename}' => $filename,
            '{hashedFilename}' => sha1($filename),
            '{variant}' => $variant,
            '{hashedVariant}' => $hashedVariant,
            '{year}' => $dateTime->format($config['dateFormat']['year']),
            '{month}' => $dateTime->format($config['dateFormat']['month']),
            '{day}' => $dateTime->format($config['dateFormat']['day']),
            '{hour}' => $dateTime->format($config['dateFormat']['hour']),
            '{minute}' => $dateTime->format($config['dateFormat']['minute']),
            '{date}' => $dateTime->format($config['dateFormat']['custom']),
        ];

        $result = $this->parseTemplate($placeholders, $template, $ds);

        $pathInfo = PathInfo::for($result);
        if (!$pathInfo->hasExtension() && substr($result, -1) === '.') {
            return substr($result, 0, -1);
        }

        return $result;
    }

    /**
     * Parses the path string template
     *
     * @param array $placeholders Assoc array of placeholder to value
     * @param string $template Template string
     * @param string $separator Directory Separator
     * @return string
     */
    protected function parseTemplate(
        array $placeholders,
        string $template,
        string $separator
    ): string {
        $result = str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $template
        );

        // Remove double or more separators caused by empty template vars
        return (string)preg_replace('/(\\\{2,})|(\/{2,})/', $separator, $result);
    }
}
