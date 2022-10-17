<?php

declare(strict_types = 1);

namespace FileStorage\View\Helper;

use Cake\Log\Log;
use Cake\View\Helper;
use Exception;
use FileStorage\Model\Entity\FileStorageEntityInterface;
use Phauthentic\Infrastructure\Storage\Processor\Exception\VariantDoesNotExistException;

/**
 * ImageHelper
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 */
class ImageHelper extends Helper
{
    /**
     * Helpers
     *
     * @var array<mixed>
     */
    protected $helpers = [
        'Html',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [
        'pathPrefix' => '',
    ];

    /**
     * Generates an image url based on the image record data and the used Gaufrette adapter to store it
     *
     * @param \FileStorage\Model\Entity\FileStorageEntityInterface|null $image FileStorage entity or whatever else table that matches this helpers needs without
     * the model, we just want the record fields
     * @param string|null $version Image version string
     * @param array $options HtmlHelper::image(), 2nd arg options array
     *
     * @return string
     */
    public function display(?FileStorageEntityInterface $image, ?string $version = null, array $options = []): string
    {
        if ($image === null) {
            return $this->fallbackImage($options, $version);
        }

        try {
            $url = $this->imageUrl($image, $version, $options);
        } catch (Exception $e) {
            Log::write('debug', $e->getMessage());
            $url = null;
        }
        if ($url !== null) {
            return $this->Html->image($url, $options);
        }

        return $this->fallbackImage($options, $version);
    }

    /**
     * URL
     *
     * @param \FileStorage\Model\Entity\FileStorageEntityInterface $image FileStorage entity or whatever else table that matches this helpers needs without
     * the model, we just want the record fields
     * @param string|null $variant Image version string
     * @param array $options HtmlHelper::image(), 2nd arg options array
     *
     * @throws \Phauthentic\Infrastructure\Storage\Processor\Exception\VariantDoesNotExistException
     *
     * @return string|null
     */
    public function imageUrl(FileStorageEntityInterface $image, ?string $variant = null, array $options = []): ?string
    {
        if ($variant === null) {
            $url = (string)$image->get('url');
            if ($url) {
                return $url;
            }
            $path = (string)$image->get('path');
        } else {
            $url = $image->getVariantUrl($variant);
            if ($url) {
                return $url;
            }
            $path = $image->getVariantPath($variant);
        }

        if (!$path) {
            throw new VariantDoesNotExistException(sprintf(
                'A variant with the name `%s` does not exists for ID `%s`',
                (string)$variant,
                (string)$image->id
            ));
        }

        $options = array_merge($this->getConfig(), $options);
        if (!empty($options['pathPrefix'])) {
            $url = $options['pathPrefix'] . $path;
        }

        return $this->normalizePath((string)$url);
    }

    /**
     * Provides a fallback image if the image record is empty
     *
     * @param array $options
     * @param string|null $version
     *
     * @return string
     */
    public function fallbackImage(array $options = [], ?string $version = null): string
    {
        if (isset($options['fallback'])) {
            if ($options['fallback'] === true) {
                $imageFile = 'placeholder/' . $version . '.jpg';
            } else {
                $imageFile = $options['fallback'];
            }
            unset($options['fallback']);

            return $this->Html->image($imageFile, $options);
        }

        return '';
    }

    /**
     * Turns the windows \ into / so that the path can be used in an url
     *
     * @param string $path
     *
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
