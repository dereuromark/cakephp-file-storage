<?php declare(strict_types=1);

namespace FileStorage\View\Helper;

use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\View\Helper;
use Cake\View\View;
use Exception;
use FileStorage\Model\Entity\FileStorageEntityInterface;
use PhpCollective\Infrastructure\Storage\Processor\Exception\VariantDoesNotExistException;

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
    protected array $helpers = [
        'Html',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'pathPrefix' => 'img/',
    ];

    /**
     * @param \Cake\View\View $view
     * @param array<string, mixed> $config
     */
    public function __construct(View $view, array $config = [])
    {
        $config += (array)Configure::read('FileStorage');

        parent::__construct($view, $config);
    }

    /**
     * Generates an image url based on the image record data and the used Gaufrette adapter to store it
     *
     * @param \FileStorage\Model\Entity\FileStorageEntityInterface|null $image FileStorage entity or whatever else table that matches this helpers needs without
     * the model, we just want the record fields
     * @param string|null $version Image version string
     * @param array<string, mixed> $options HtmlHelper::image(), 2nd arg options array
     *
     * @return string
     */
    public function display(?FileStorageEntityInterface $image, ?string $version = null, array $options = []): string
    {
        if (!($image instanceof FileStorageEntityInterface)) {
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
     * @param array<string, mixed> $options HtmlHelper::image(), 2nd arg options array
     *
     * @throws \PhpCollective\Infrastructure\Storage\Processor\Exception\VariantDoesNotExistException
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
                (string)$image->get('id'),
            ));
        }

        $options += $this->getConfig();
        if (!empty($options['pathPrefix'])) {
            $url = '/' . $options['pathPrefix'] . $path;
        }

        return $this->normalizePath((string)$url);
    }

    /**
     * Render a `<picture>` element with one `<source>` per modern image
     * format (AVIF, WebP) and a fallback `<img>` tag pointing at the
     * traditional encoding.
     *
     * Browsers pick the first `<source>` whose `type` they understand;
     * everything else falls through to the inner `<img>`. The plugin doesn't
     * encode AVIF or WebP on its own — the caller is expected to declare
     * those as variants in `FileStorage.imageVariants` config (e.g. a
     * `medium.webp` variant alongside the `medium` JPEG variant) so the
     * existing pipeline writes them. The helper just looks them up.
     *
     * Convention: a `$format` source is fetched as `imageUrl($image,
     * "$version.$format")` (or `imageUrl($image, $format)` when no version
     * is requested). If a format variant isn't defined for the entity
     * (`VariantDoesNotExistException`) or the variant URL is empty, the
     * source for that format is silently skipped — browsers degrade
     * gracefully to the next entry. Unexpected lookup errors are logged
     * to the `debug` channel and the format is still skipped.
     *
     * Options:
     * - `formats` (array): list of format identifiers in preference order.
     *   Default `['avif', 'webp']` — AVIF first because where supported
     *   it's the most efficient; WebP as a near-universal fallback; the
     *   original encoding inside the `<img>` for the long tail.
     * - All other options are forwarded to `imageUrl()` / `Html->image()`.
     *
     * @param \FileStorage\Model\Entity\FileStorageEntityInterface|null $image
     * @param string|null $version
     * @param array<string, mixed> $options
     *
     * @return string `<picture>...</picture>` (or just the `<img>` when no
     *     alternate-format variants exist).
     */
    public function picture(
        ?FileStorageEntityInterface $image,
        ?string $version = null,
        array $options = [],
    ): string {
        $formats = $options['formats'] ?? ['avif', 'webp'];
        unset($options['formats']);

        if ($image === null) {
            return $this->fallbackImage($options, $version);
        }

        $sources = [];
        foreach ((array)$formats as $format) {
            if (!is_string($format) || $format === '') {
                continue;
            }
            $variantName = $version !== null && $version !== '' ? $version . '.' . $format : $format;
            try {
                $url = $this->imageUrl($image, $variantName, $options);
            } catch (VariantDoesNotExistException) {
                // Expected when an alt-format variant hasn't been generated
                // for this entity yet — degrade silently. Logging every miss
                // produces noise on apps that haven't backfilled AVIF/WebP.
                continue;
            }
            if ($url === null || $url === '') {
                continue;
            }
            $sources[] = [
                'srcset' => $url,
                'type' => 'image/' . $format,
            ];
        }

        // The fallback img is always emitted, even when alt-format variants
        // exist — `<picture>` semantics require it as the final child.
        $fallback = $this->display($image, $version, $options);

        if (!$sources) {
            // No alt-format variants found; the bare img is equally good
            // and avoids the visual cost of wrapping the only child in
            // a redundant `<picture>` element.
            return $fallback;
        }

        $sourceHtml = '';
        foreach ($sources as $source) {
            $sourceHtml .= sprintf(
                '<source srcset="%s" type="%s">',
                h($source['srcset']),
                h($source['type']),
            );
        }

        return sprintf('<picture>%s%s</picture>', $sourceHtml, $fallback);
    }

    /**
     * Provides a fallback image if the image record is empty
     *
     * @param array<string, mixed> $options
     * @param string|null $version
     *
     * @return string
     */
    public function fallbackImage(array $options = [], ?string $version = null): string
    {
        if (isset($options['fallback'])) {
            $imageFile = $options['fallback'] === true ? 'placeholder/' . $version . '.jpg' : $options['fallback'];
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
