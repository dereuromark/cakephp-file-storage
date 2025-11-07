<?php declare(strict_types=1);

namespace FileStorage\FileStorage;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use PhpCollective\Infrastructure\Storage\File;
use PhpCollective\Infrastructure\Storage\FileInterface;

/**
 * Converts the Cake Entity to a File Storage Object and vice versa
 */
class DataTransformer implements DataTransformerInterface
{
    protected Table $table;

    /**
     * @param \Cake\ORM\Table $table Table
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity
     *
     * @return \PhpCollective\Infrastructure\Storage\FileInterface
     */
    public function entityToFileObject(EntityInterface $entity): FileInterface
    {
        $file = File::create(
            (string)$entity->get('filename'),
            (int)$entity->get('filesize'),
            (string)$entity->get('mime_type'),
            (string)$entity->get('adapter'),
            (string)$entity->get('collection'),
            (string)$entity->get('model'),
            (string)$entity->get('foreign_key'),
            (array)$entity->get('metadata'),
            (array)$entity->get('variants'),
        );

        $file = $file->withUuid((string)$entity->get('id'));

        if ($entity->has('path')) {
            $file = $file->withPath($entity->get('path'));
        }

        if ($entity->has('file')) {
            /** @var \Psr\Http\Message\UploadedFileInterface|array $uploadedFile */
            $uploadedFile = $entity->get('file');
            if (!is_array($uploadedFile)) {
                $filename = $uploadedFile->getStream()->getMetadata('uri');
            } else {
                $filename = $uploadedFile['tmp_name'];
            }

            $file = $file->withFile($filename);
        }

        return $file;
    }

    /**
     * @param \PhpCollective\Infrastructure\Storage\FileInterface $file
     * @param \Cake\Datasource\EntityInterface|null $entity
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function fileObjectToEntity(FileInterface $file, ?EntityInterface $entity): EntityInterface
    {
        $data = [
            'id' => $file->uuid(),
            'model' => $file->model(),
            'foreign_key' => $file->modelId(),
            'collection' => $file->collection(), // aka alias
            'filesize' => $file->filesize(),
            'filename' => $file->filename(),
            'mime_type' => $file->mimeType(),
            'variants' => $file->variants(),
            'metadata' => $file->metadata(),
            'adapter' => $file->storage(),
            'path' => $file->path(),
        ];

        if ($entity) {
            return $this->table->patchEntity($entity, $data, ['validate' => false, 'guard' => false]);
        }

        // For new entities, create without ID first, then set it directly
        // because $_accessible['id'] = false prevents mass assignment
        $uuid = $data['id'];
        unset($data['id']);
        /** @var \Cake\ORM\Entity $entity */
        $entity = $this->table->newEntity($data, ['validate' => false, 'guard' => false]);
        $entity->id = $uuid;

        return $entity;
    }
}
