<?php
declare(strict_types=1);

namespace Burzum\FileStorage\Model\Behavior;

use ArrayObject;
use Burzum\FileStorage\FileStorage\DataTransformer;
use Burzum\FileStorage\FileStorage\DataTransformerInterface;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use League\Flysystem\AdapterInterface;
use Phauthentic\Infrastructure\Storage\FileInterface;
use Phauthentic\Infrastructure\Storage\FileStorage;
use Phauthentic\Infrastructure\Storage\Processor\ProcessorInterface;
use RuntimeException;
use Throwable;

/**
 * File Storage Behavior.
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 */
class FileStorageBehavior extends Behavior
{
    use EventDispatcherTrait;

    /**
     * @var \Phauthentic\Infrastructure\Storage\FileStorage
     */
    protected FileStorage $fileStorage;

    /**
     * @var \Phauthentic\Infrastructure\Storage\Processor\ProcessorInterface
     */
    protected ?ProcessorInterface $imageProcessor;

    /**
     * @var \Burzum\FileStorage\FileStorage\DataTransformerInterface
     */
    protected DataTransformerInterface $transformer;

    /**
     * Default config
     *
     * @var array
     */
    protected $_defaultConfig = [
        'defaultStorageConfig' => 'Local',
        'ignoreEmptyFile' => true,
        'fileField' => 'file',
        'fileStorage' => null,
        'imageProcessor' => null
    ];

    /**
     * @var array
     */
    protected array $processors = [];

    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        if ($this->getConfig('fileStorage') instanceOf FileStorage) {
            $this->fileStorage = $this->getConfig('fileStorage');
        } else {
           throw new RuntimeException(
                'Missing or invalid fileStorage config key'
           );
        }

        if (!$this->getConfig('dataTransformer') instanceof DataTransformerInterface) {
            $this->transformer = new DataTransformer(
                $this->getTable()
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     * @param string $configName
     * @return \League\Flysystem\AdapterInterface
     */
    public function getStorageAdapter(string $configName): AdapterInterface
    {
        return $this->fileStorage->getStorage($configName);
    }

    /**
     * Checks if a file upload is present.
     *
     * @param \Cake\Datasource\EntityInterface|\ArrayObject $entity
     * @return bool
     */
    protected function isFileUploadPresent($entity)
    {
        $field = $this->getConfig('fileField');
        if ($this->getConfig('ignoreEmptyFile') === true) {
            if (!isset($entity[$field])) {
                return false;
            }

            /** @var \Psr\Http\Message\UploadedFileInterface|array $file */
            $file = $entity[$field];
            if (!is_array($file)) {
                return $file->getError() !== UPLOAD_ERR_NO_FILE;
            }

            return $file['error'] !== UPLOAD_ERR_NO_FILE;
        }

        return true;
    }

    /**
     * beforeMarshal callback
     *
     * @param \Cake\Event\EventInterface $event
     * @param \ArrayObject $data
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        if ($this->isFileUploadPresent($data)) {
            $this->getFileInfoFromUpload($data);
        }
    }

    /**
     * beforeSave callback
     *
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$this->isFileUploadPresent($entity)) {
            return;
        }

        $this->checkEntityBeforeSave($entity);

        $this->dispatchEvent('FileStorage.beforeSave', [
            'entity' => $entity,
            'storageAdapter' => $this->getStorageAdapter($entity->get('adapter'))
        ], $this->getTable());
    }

    /**
     * afterSave callback
     *
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$this->isFileUploadPresent($entity)) {
            return;
        }

        if ($entity->isNew()) {
            try {
                $file = $this->entityToFileObject($entity);
                $file = $this->fileStorage->store($file);
                $file = $this->processImages($file, $entity);

                foreach ($this->processors as $processor) {
                    $file = $processor->process($file);
                }

                $entity = $this->fileObjectToEntity($file, $entity);
                $this->getTable()->save(
                    $entity,
                    ['callbacks' => false]
                );
            } catch (Throwable $exception) {
                $this->getTable()->delete($entity);
                throw $exception;
            }
        }

        $this->dispatchEvent('FileStorage.afterSave', [
            'entity' => $entity,
            'storageAdapter' => $this->getStorageAdapter($entity->get('adapter'))
        ], $this->getTable());
    }

    /**
     * checkEntityBeforeSave
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @return void
     */
    protected function checkEntityBeforeSave(EntityInterface $entity)
    {
        if ($entity->isNew()) {
            if (!$entity->has('model')) {
                $entity->set('model', $this->getTable()->getTable());
            }

            if (!$entity->has('adapter')) {
                $entity->set('adapter', $this->getConfig('defaultStorageConfig'));
            }
        }
    }

    /**
     * afterDelete callback
     *
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $this->dispatchEvent('FileStorage.afterDelete', [
            'entity' => $entity,
        ], $this->getTable());

        $file = $this->entityToFileObject($entity);
        $this->fileStorage->remove($file);
    }

    /**
     * Gets information about the file that is being uploaded.
     *
     * - gets the file size
     * - gets the mime type
     * - gets the extension if present
     *
     * @param array|\ArrayAccess $upload
     * @param string $field
     * @return void
     */
    protected function getFileInfoFromUpload(&$upload, $field = 'file')
    {
        /** @var \Psr\Http\Message\UploadedFileInterface|array $uploadedFile */
        $uploadedFile = $upload[$field];
        if (!is_array($uploadedFile)) {
            $upload['filesize'] = $uploadedFile->getSize();
            $upload['mime_type'] = $uploadedFile->getClientMediaType();
            $upload['extension'] = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
            $upload['filename'] = $uploadedFile->getClientFilename();
        } else {
            $upload['filesize'] = $uploadedFile['size'];
            $upload['mime_type'] = $uploadedFile['type'];
            $upload['extension'] = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $upload['filename'] = $uploadedFile['name'];
        }
    }

    /**
     * Don't use Table::deleteAll() if you don't want to end up with orphaned
     * files! The reason for that is that deleteAll() doesn't fire the
     * callbacks. So the events that will remove the files won't get fired.
     *
     * @param array $conditions Query::where() array structure.
     * @return int Number of deleted records / files
     */
    public function deleteAllFiles($conditions)
    {
        $table = $this->getTable();

        $results = $table->find()
            ->select((array)$table->getPrimaryKey())
            ->where($conditions)
            ->all();

        if ($results->count() > 0) {
            foreach ($results as $result) {
                $table->delete($result);
            }
        }

        return $results->count();
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @return \Phauthentic\Infrastructure\Storage\FileInterface
     */
    public function entityToFileObject(EntityInterface $entity): FileInterface
    {
        return $this->transformer->entityToFileObject($entity);
    }

    /**
     * @param \Phauthentic\Infrastructure\Storage\FileInterface $file File
     * @param \Cake\Datasource\EntityInterface|null $entity
     * @return \Cake\Datasource\EntityInterface
     */
    public function fileObjectToEntity(FileInterface $file, ?EntityInterface $entity)
    {
        return $this->transformer->fileObjectToEntity($file, $entity);
    }

    /**
     * Processes images
     *
     * @param \Phauthentic\Infrastructure\Storage\FileInterface $file File
     * @param \Cake\Datasource\EntityInterface $entity
     * @return \Phauthentic\Infrastructure\Storage\FileInterface
     */
    public function processImages(FileInterface $file, EntityInterface $entity): FileInterface
    {
        $imageSizes = Configure::read('FileStorage.imageVariants');
        $model = $file->model();
        $identifier = $entity->get('identifier');

        if (!isset($imageSizes[$model][$identifier])) {
            return $file;
        }

        $file = $file->withVariants($imageSizes[$model][$identifier]);
        $file = $this->imageProcessor->process($file);

        return $file;
    }

    /**
     * @return \Phauthentic\Infrastructure\Storage\Processor\ProcessorInterface
     */
    protected function getImageProcessor(): ProcessorInterface
    {
        if ($this->imageProcessor !== null) {
            return $this->imageProcessor;
        }

        if ($this->getConfig('imageProcessor') instanceOf ProcessorInterface) {
            $this->imageProcessor = $this->getConfig('imageProcessor');
        }

        if ($this->imageProcessor === null) {
            throw new RuntimeException('No image processor found');
        }

        return $this->imageProcessor;
    }
}
