<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Closure;
use FileStorage\Exception\BlobAttachDeniedException;
use FileStorage\Exception\BlobNotAvailableException;
use FileStorage\Model\Behavior\FileStorageBehavior;
use FileStorage\Model\Entity\FileStorage;
use InvalidArgumentException;
use League\Flysystem\UnableToRetrieveMetadata;

/**
 * Creates file rows referencing stored blobs without an upload.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobAttacher
{
    /**
     * @var string
     */
    protected const DEFAULT_ADAPTER = 'Local';

    protected Table $files;

    public function __construct(?Table $files = null)
    {
        $this->files = $files ?? TableRegistry::getTableLocator()->get('FileStorage.FileStorage');
    }

    /**
     * Exposing this check to clients tells them whether content exists.
     * No authorization, storage access, or locking is performed.
     */
    public function has(string $hash, ?string $adapter = null): bool
    {
        return $this->files->getConnection()->execute(
            'SELECT id FROM file_storage_blobs WHERE adapter = :adapter AND hash = :hash AND path IS NOT NULL LIMIT 1',
            ['adapter' => $adapter ?? $this->defaultAdapter(), 'hash' => $hash],
        )->fetch('assoc') !== false;
    }

    public function userOwnsHash(string|int $userId, string $hash): bool
    {
        return $this->files->exists(['user_id' => $userId, 'hash' => $hash]);
    }

    /**
     * No variants are generated. Applications can queue ImageVariantTask for the new row.
     *
     * @param string $hash
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return \FileStorage\Model\Entity\FileStorage
     */
    public function attach(string $hash, array $data, array $context = []): FileStorage
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new InvalidArgumentException('A lowercase SHA-256 hash is required.');
        }
        if (!is_string($data['model'] ?? null) || !is_string($data['filename'] ?? null) || $data['filename'] === '') {
            throw new InvalidArgumentException('Model and a non-empty filename are required.');
        }
        $collection = $data['collection'] ?? null;
        if ($collection !== null && !is_string($collection)) {
            throw new InvalidArgumentException('Collection must be a string or null.');
        }
        if (!DeduplicationConfig::isEnabled($data['model'], $collection)) {
            throw new BlobAttachDeniedException('The model and collection do not enable deduplication.');
        }
        $authorizer = Configure::read('FileStorage.deduplicate.attachAuthorizer');
        if (!$authorizer instanceof Closure || $authorizer($hash, $data, $context) !== true) {
            throw new BlobAttachDeniedException('Attaching this content is not authorized.');
        }
        $adapterName = $data['adapter'] ?? $this->defaultAdapter();
        if (!is_string($adapterName) || $adapterName === '') {
            throw new InvalidArgumentException('Adapter must be a non-empty string.');
        }

        return $this->files->getConnection()->transactional(function () use ($hash, $data, $collection, $adapterName): FileStorage {
            $claim = (new BlobRegistry($this->files))->claim($adapterName, $hash, DateTime::now());
            if ($claim->path === null) {
                throw new BlobNotAvailableException('The content is not stored.');
            }
            /** @var \FileStorage\Model\Behavior\FileStorageBehavior $behavior */
            $behavior = $this->files->getBehavior('FileStorage');
            $adapter = $behavior->getStorageAdapter($adapterName);
            if (!$adapter->fileExists($claim->path)) {
                throw new BlobNotAvailableException('The stored content is missing.');
            }
            $mimeType = $data['mime_type'] ?? null;
            if (!array_key_exists('mime_type', $data)) {
                try {
                    $mimeType = $adapter->mimeType($claim->path)->mimeType();
                } catch (UnableToRetrieveMetadata) {
                    $mimeType = null;
                }
            }
            $options = [FileStorageBehavior::OPTION_ATTACH => true];
            /** @var \FileStorage\Model\Entity\FileStorage $entity */
            $entity = $this->files->newEntity([
                'model' => $data['model'],
                'collection' => $collection,
                'foreign_key' => $data['foreign_key'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'filename' => $data['filename'],
                'extension' => pathinfo($data['filename'], PATHINFO_EXTENSION),
                'filesize' => $adapter->fileSize($claim->path)->fileSize(),
                'mime_type' => $mimeType,
                'hash' => $hash,
                'path' => $claim->path,
                'adapter' => $adapterName,
                'blob_id' => $claim->id,
                'variants' => [],
                'metadata' => [],
            ], $options);
            $entity->set('uuid', Text::uuid());
            $this->files->saveOrFail($entity, $options);
            $this->files->dispatchEvent('FileStorage.blobAttached', ['entity' => $entity]);

            return $entity;
        });
    }

    protected function defaultAdapter(): string
    {
        return Configure::read('FileStorage.behaviorConfig.defaultStorageConfig', static::DEFAULT_ADAPTER);
    }
}
