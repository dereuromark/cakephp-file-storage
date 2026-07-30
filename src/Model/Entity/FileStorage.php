<?php declare(strict_types=1);

namespace FileStorage\Model\Entity;

use Cake\Log\Log;
use Cake\ORM\Entity;

/**
 * FileStorage Entity.
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 *
 * @property array $variants
 * @property array $metadata
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property int|string|null $foreign_key
 * @property string|null $model
 * @property string|null $filename
 * @property int|null $filesize
 * @property string|null $mime_type
 * @property string|null $extension
 * @property string|null $hash
 * @property string|null $path
 * @property string|null $adapter
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property string|null $collection
 */
class FileStorage extends Entity implements FileStorageEntityInterface
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false,
        'uuid' => false,
    ];

    /**
     * Public/storage identity for URLs and adapter-facing references.
     *
     * @return string
     */
    public function publicId(): string
    {
        return (string)$this->uuid;
    }

    /**
     * The decoded variants map.
     *
     * The column is json, but the value is not guaranteed to arrive decoded: on
     * a text column without a matching select type map entry the raw JSON string
     * comes through. Casting that with (array) would wrap the string instead of
     * decoding it, so every lookup below would silently miss.
     *
     * @return array<string, mixed>
     */
    public function variants(): array
    {
        $variants = $this->get('variants');
        if (is_string($variants)) {
            $variants = json_decode($variants, true);
        }

        return is_array($variants) ? $variants : [];
    }

    /**
     * @param string $variant Variant
     *
     * @return string|null
     */
    public function getVariantUrl(string $variant): ?string
    {
        $variants = $this->variants();
        if (!isset($variants[$variant]['url'])) {
            return null;
        }

        if (!is_string($variants[$variant]['url'])) {
            Log::write('error', 'Invalid variants url data for ' . $this->id);

            // Return first element without modifying the array
            if (is_array($variants[$variant]['url']) && count($variants[$variant]['url']) > 0) {
                return (string)reset($variants[$variant]['url']);
            }

            return null;
        }

        return $variants[$variant]['url'];
    }

    /**
     * @param string $variant Variant
     *
     * @return string|null
     */
    public function getVariantPath(string $variant): ?string
    {
        $variants = $this->variants();
        if (!isset($variants[$variant]['path'])) {
            return null;
        }

        if (!is_string($variants[$variant]['path'])) {
            Log::write('error', 'Invalid variants path data for ' . $this->id);

            // Return first element without modifying the array
            if (is_array($variants[$variant]['path']) && count($variants[$variant]['path']) > 0) {
                return (string)reset($variants[$variant]['path']);
            }

            return null;
        }

        return $variants[$variant]['path'];
    }
}
