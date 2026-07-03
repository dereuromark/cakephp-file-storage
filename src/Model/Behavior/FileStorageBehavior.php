<?php declare(strict_types=1);

namespace FileStorage\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\Utility\Text;
use FileStorage\FileStorage\DataTransformer;
use FileStorage\FileStorage\DataTransformerInterface;
use FileStorage\Model\Validation\UploadValidatorInterface;
use League\Flysystem\FilesystemAdapter;
use PhpCollective\Infrastructure\Storage\FileInterface;
use PhpCollective\Infrastructure\Storage\FileStorage;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;
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

    protected FileStorage $fileStorage;

    protected ?DataTransformerInterface $transformer = null;

    protected ?ProcessorInterface $processor = null;

    /**
     * Default config
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'defaultStorageConfig' => 'Local',
        'ignoreEmptyFile' => true,
        'fileField' => 'file',
        'fileStorage' => null,
        'fileProcessor' => null,
        'fileValidator' => null,
    ];

    /**
     * @inheritDoc
     *
     * @throws \RuntimeException
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        if (!($this->getConfig('fileStorage') instanceof FileStorage)) {
            throw new RuntimeException(
                'Missing or invalid fileStorage config key',
            );
        }

        $this->fileStorage = $this->getConfig('fileStorage');
    }

    /**
     * @param string $configName
     *
     * @return \League\Flysystem\FilesystemAdapter
     */
    public function getStorageAdapter(string $configName): FilesystemAdapter
    {
        return $this->fileStorage->getStorage($configName);
    }

    /**
     * Checks if a file upload is present.
     *
     * @param \FileStorage\Model\Entity\FileStorage|\ArrayObject $entity
     *
     * @return bool
     */
    protected function isFileUploadPresent($entity): bool
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
     * @param \ArrayObject $options
     *
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        if ($this->getConfig('fileValidator')) {
            $this->configureValidator();
        }

        if ($this->isFileUploadPresent($data)) {
            $this->getFileInfoFromUpload($data);
        }
    }

    /**
     * beforeSave callback
     *
     * @param \Cake\Event\EventInterface $event
     * @param \FileStorage\Model\Entity\FileStorage $entity
     * @param \ArrayObject $options
     *
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$this->isFileUploadPresent($entity)) {
            $event->stopPropagation();
            $event->setResult(false);

            return;
        }

        $this->checkEntityBeforeSave($entity);

        $this->dispatchEvent('FileStorage.beforeSave', [
            'entity' => $entity,
            'storageAdapter' => $this->getStorageAdapter($entity->get('adapter')),
        ], $this->table());
    }

    /**
     * afterSave callback
     *
     * @param \Cake\Event\EventInterface $event
     * @param \FileStorage\Model\Entity\FileStorage $entity
     * @param \ArrayObject $options
     *
     * @throws \Exception
     *
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$this->isFileUploadPresent($entity)) {
            return;
        }

        if ($entity->isDirty()) {
            // Track the file as it moves through store → processImages → process so
            // the rollback in catch{} can delete whatever made it to disk. Without
            // this the previous behavior deleted the DB row but left the file
            // (and any partially-written variants) orphaned on the storage adapter.
            $storedFile = null;
            try {
                $file = $this->entityToFileObject($entity);

                $this->dispatchEvent('FileStorage.beforeStoringFile', [
                    'entity' => $entity,
                    'file' => $file,
                ], $this->table());

                $file = $this->fileStorage->store($file);
                $storedFile = $file;

                $this->dispatchEvent('FileStorage.afterStoringFile', [
                    'entity' => $entity,
                    'file' => $file,
                ], $this->table());

                $file = $this->processImages($file, $entity);
                // Move the cleanup handle forward BEFORE process() runs: the
                // processor may write some variants to disk and then throw
                // partway through. By the time we hit catch{}, the variant
                // paths configured here live on $file, so passing this $file
                // to fileStorage->remove() can clean up partially-written
                // variants alongside the main blob (remove() skips variants
                // whose path key is unset, so unwritten ones are no-ops).
                $storedFile = $file;

                $processor = $this->getFileProcessor();

                $this->dispatchEvent('FileStorage.beforeFileProcessing', [
                    'entity' => $entity,
                    'file' => $file,
                ], $this->table());

                $file = $processor->process($file);
                $storedFile = $file;

                $this->dispatchEvent('FileStorage.afterFileProcessing', [
                    'entity' => $entity,
                    'file' => $file,
                ], $this->table());
                $entity = $this->fileObjectToEntity($file, $entity);

                $tableConfig = $this->table()->behaviors()->get('FileStorage')->getConfig();
                if (empty($tableConfig['className'])) {
                    $tableConfig['className'] = 'FileStorage.FileStorage';
                }
                // Temporarily remove behavior to prevent recursion when saving metadata.
                // try/finally so that even if saveOrFail() throws (and the outer catch
                // deletes the entity + rethrows), the table doesn't end up permanently
                // missing this behavior — the TableLocator can cache the same instance
                // across the rest of the request.
                $this->table()->removeBehavior('FileStorage');
                try {
                    $this->table()->saveOrFail($entity, ['checkRules' => false]);
                } finally {
                    $this->table()->addBehavior('FileStorage', $tableConfig);
                }
            } catch (Throwable $exception) {
                $this->table()->delete($entity);
                if ($storedFile !== null) {
                    // Best-effort cleanup; if the storage adapter is itself the
                    // source of the failure (e.g. the bucket went away), we
                    // don't want to mask the original exception.
                    try {
                        $this->fileStorage->remove($storedFile);
                    } catch (Throwable) {
                        // Swallow: the original $exception is the real story.
                    }
                }

                throw $exception;
            }
        }

        $this->dispatchEvent('FileStorage.afterSave', [
            'entity' => $entity,
            'storageAdapter' => $this->getStorageAdapter($entity->get('adapter')),
        ], $this->table());
    }

    /**
     * @param \FileStorage\Model\Entity\FileStorage $entity
     *
     * @return void
     */
    protected function checkEntityBeforeSave(EntityInterface $entity): void
    {
        if ($entity->isNew()) {
            if (!$entity->get('uuid')) {
                $entity->set('uuid', Text::uuid());
            }

            if (!$entity->has('adapter')) {
                $entity->set('adapter', $this->getConfig('defaultStorageConfig'));
            }

            return;
        }

        $entity->variants = [];
        $entity->metadata = [];
    }

    /**
     * afterDelete callback
     *
     * @param \Cake\Event\EventInterface $event
     * @param \FileStorage\Model\Entity\FileStorage $entity
     * @param \ArrayObject $options
     *
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $this->dispatchEvent('FileStorage.afterDelete', [
            'entity' => $entity,
        ], $this->table());

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
     * @param \ArrayAccess|array $upload
     * @param string $field
     *
     * @return void
     */
    protected function getFileInfoFromUpload(&$upload, string $field = 'file'): void
    {
        /** @var \Psr\Http\Message\UploadedFileInterface|array $uploadedFile */
        $uploadedFile = $upload[$field];
        if (!is_array($uploadedFile)) {
            $upload['filesize'] = $uploadedFile->getSize();
            $upload['mime_type'] = $uploadedFile->getClientMediaType();
            $upload['extension'] = pathinfo((string)$uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
            $upload['filename'] = $uploadedFile->getClientFilename();
        } else {
            $upload['filesize'] = $uploadedFile['size'];
            $upload['mime_type'] = $uploadedFile['type'];
            $upload['extension'] = pathinfo((string)$uploadedFile['name'], PATHINFO_EXTENSION);
            $upload['filename'] = $uploadedFile['name'];
        }
    }

    /**
     * Don't use Table::deleteAll() if you don't want to end up with orphaned
     * files! The reason for that is that deleteAll() doesn't fire the
     * callbacks. So the events that will remove the files won't get fired.
     *
     * @param array $conditions Query::where() array structure.
     *
     * @return int Number of deleted records / files
     */
    public function deleteAllFiles(array $conditions): int
    {
        $table = $this->table();

        $results = $table->find()
            ->select((array)$table->getPrimaryKey())
            ->where($conditions)
            ->all();

        if ($results->count() > 0) {
            /** @var \Cake\Datasource\EntityInterface $result */
            foreach ($results as $result) {
                $table->delete($result);
            }
        }

        return $results->count();
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity Entity
     *
     * @return \PhpCollective\Infrastructure\Storage\FileInterface
     */
    public function entityToFileObject(EntityInterface $entity): FileInterface
    {
        return $this->getTransformer()->entityToFileObject($entity);
    }

    /**
     * @param \PhpCollective\Infrastructure\Storage\FileInterface $file File
     * @param \Cake\Datasource\EntityInterface|null $entity
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function fileObjectToEntity(FileInterface $file, ?EntityInterface $entity): EntityInterface
    {
        return $this->getTransformer()->fileObjectToEntity($file, $entity);
    }

    /**
     * Processes images
     *
     * @param \PhpCollective\Infrastructure\Storage\FileInterface $file File
     * @param \FileStorage\Model\Entity\FileStorage $entity
     *
     * @return \PhpCollective\Infrastructure\Storage\FileInterface
     */
    public function processImages(FileInterface $file, EntityInterface $entity): FileInterface
    {
        $imageSizes = (array)Configure::read('FileStorage.imageVariants');

        $collection = $entity->get('collection');
        $model = (string)$entity->get('model');

        if (!isset($imageSizes[$model][$collection])) {
            return $file;
        }

        return $file->withVariants($imageSizes[$model][$collection]);
    }

    /**
     * @throws \RuntimeException
     *
     * @return \PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface
     */
    protected function getFileProcessor(): ProcessorInterface
    {
        if ($this->processor instanceof ProcessorInterface) {
            return $this->processor;
        }

        if ($this->getConfig('fileProcessor') instanceof ProcessorInterface) {
            $this->processor = $this->getConfig('fileProcessor');
        }

        if (!($this->processor instanceof ProcessorInterface)) {
            throw new RuntimeException('No processor found');
        }

        return $this->processor;
    }

    /**
     * @return \FileStorage\FileStorage\DataTransformerInterface
     */
    protected function getTransformer(): DataTransformerInterface
    {
        if ($this->transformer instanceof DataTransformerInterface) {
            return $this->transformer;
        }

        if ($this->getConfig('dataTransformer') instanceof DataTransformerInterface) {
            $this->transformer = $this->getConfig('dataTransformer');
        } else {
            $this->transformer = new DataTransformer(
                $this->table(),
            );
        }

        return $this->transformer;
    }

    /**
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function configureValidator(): void
    {
        /** @var \FileStorage\Model\Validation\UploadValidatorInterface|class-string<\FileStorage\Model\Validation\UploadValidatorInterface>|null $validator */
        $validator = $this->getConfig('fileValidator');
        if (is_string($validator)) {
            $validator = new $validator();
        }

        if (!($validator instanceof UploadValidatorInterface)) {
            throw new RuntimeException('Validator must implement ' . UploadValidatorInterface::class);
        }

        $validator->configure($this->table()->getValidator());
    }
}
