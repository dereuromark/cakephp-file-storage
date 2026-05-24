<?php declare(strict_types=1);

namespace FileStorage\Queue\Task;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use FileStorage\FileStorage\DataTransformer;
use FileStorage\FileStorage\DataTransformerInterface;
use PhpCollective\Infrastructure\Storage\FileInterface;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;
use Queue\Model\QueueException;
use Queue\Queue\Task;
use RuntimeException;

/**
 * Regenerate image variants for a single FileStorage entity in the background.
 *
 * The `ImageVariantGenerateCommand` runs inline and blocks the calling shell
 * for the whole batch — fine for one-off CLI use, but unusable from an admin
 * UI button on a deployment with thousands of attachments. This task does the
 * same per-entity work but as a dispatchable Queue plugin job, so:
 *
 * - the admin "regenerate variants" action can enqueue 1-job-per-entity and
 *   return immediately;
 * - failed jobs auto-retry per the queue config;
 * - the renderer's CPU cost spreads across multiple workers naturally.
 *
 * Job data shape:
 *
 * ```php
 * [
 *     'id' => 'fs-entity-uuid-or-id', // REQUIRED — FileStorage row id
 *     'operations' => [ // REQUIRED — variant config map,
 *         'thumbnail' => ['width' => 100], // same shape `imageVariants` uses.
 *         'medium' => ['width' => 600],
 *     ],
 *     'merge' => true, // optional, default true. Merge
 *                                             // means existing variants stay;
 *                                             // false replaces the variant set.
 *     'storageTable' => 'FileStorage.FileStorage', // optional, custom table.
 * ]
 * ```
 *
 * Soft-fails (returns early, no exception, logs nothing) when the entity is
 * missing — a regeneration job for a since-deleted file is not an error.
 * Throws `QueueException` for misconfigured payloads so the queue worker can
 * apply its retry / dead-letter behavior.
 */
class ImageVariantTask extends Task
{
    use EventDispatcherTrait;
    use LocatorAwareTrait;

    /**
     * @var int|null
     */
    public ?int $timeout = 300;

    /**
     * @var int|null
     */
    public ?int $retries = 1;

    protected ?DataTransformerInterface $transformer = null;

    protected ?ProcessorInterface $processor = null;

    protected Table $storageTable;

    /**
     * @param array<string, mixed> $data
     * @param int $jobId
     *
     * @throws \Queue\Model\QueueException For misconfigured payloads.
     *
     * @return void
     */
    public function run(array $data, int $jobId): void
    {
        if (empty($data['id'])) {
            throw new QueueException('ImageVariantTask called without required `id` data key.');
        }
        if (empty($data['operations']) || !is_array($data['operations'])) {
            throw new QueueException('ImageVariantTask called without required `operations` data key.');
        }

        $storageTableName = is_string($data['storageTable'] ?? null) && $data['storageTable'] !== ''
            ? $data['storageTable']
            : 'FileStorage.FileStorage';
        $this->storageTable = TableRegistry::getTableLocator()->get($storageTableName);

        /** @var \FileStorage\Model\Entity\FileStorage|null $entity */
        $entity = $this->storageTable->find()->where(['id' => $data['id']])->first();
        if ($entity === null) {
            // File was removed since the job was queued — no-op. Logging an
            // error here would just produce noise during normal cleanup.
            return;
        }

        $merge = (bool)($data['merge'] ?? true);
        $this->processEntity($entity, $data['operations'], $merge);
    }

    /**
     * Apply the variant operations to one entity and persist the updated row.
     *
     * @param \FileStorage\Model\Entity\FileStorage $entity
     * @param array<string, mixed> $operations
     * @param bool $merge
     *
     * @return void
     */
    protected function processEntity(EntityInterface $entity, array $operations, bool $merge = true): void
    {
        $file = $this->entityToFileObject($entity);
        $file = $file->withVariants($operations, $merge);

        $processor = $this->getFileProcessor();

        $this->dispatchEvent('FileStorage.beforeFileProcessing', [
            'entity' => $entity,
            'file' => $file,
        ], $this->storageTable);

        $file = $processor->process($file);

        $this->dispatchEvent('FileStorage.afterFileProcessing', [
            'entity' => $entity,
            'file' => $file,
        ], $this->storageTable);

        $entity = $this->fileObjectToEntity($file, $entity);

        // Same recursion-guard pattern as ImageVariantGenerateCommand and the
        // FileStorageBehavior — strip the behavior for the metadata save so
        // the afterSave processor pipeline doesn't re-fire here. Tracking
        // presence separately from config lets us restore the behavior even
        // when it was attached with an empty options array.
        $behaviors = $this->storageTable->behaviors();
        $hadBehavior = $behaviors->has('FileStorage');
        $tableConfig = $hadBehavior ? $behaviors->get('FileStorage')->getConfig() : [];
        if ($hadBehavior) {
            $this->storageTable->removeBehavior('FileStorage');
        }
        try {
            $this->storageTable->saveOrFail($entity);
        } finally {
            if ($hadBehavior) {
                $this->storageTable->addBehavior('FileStorage.FileStorage', $tableConfig);
            }
        }
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

        $processor = Configure::read('FileStorage.behaviorConfig.fileProcessor');
        if (!$processor instanceof ProcessorInterface) {
            throw new RuntimeException('No FileStorage processor configured.');
        }
        $this->processor = $processor;

        return $this->processor;
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity
     *
     * @return \PhpCollective\Infrastructure\Storage\FileInterface
     */
    protected function entityToFileObject(EntityInterface $entity): FileInterface
    {
        return $this->getTransformer()->entityToFileObject($entity);
    }

    /**
     * @param \PhpCollective\Infrastructure\Storage\FileInterface $file
     * @param \Cake\Datasource\EntityInterface|null $entity
     *
     * @return \Cake\Datasource\EntityInterface
     */
    protected function fileObjectToEntity(FileInterface $file, ?EntityInterface $entity): EntityInterface
    {
        return $this->getTransformer()->fileObjectToEntity($file, $entity);
    }

    /**
     * @return \FileStorage\FileStorage\DataTransformerInterface
     */
    protected function getTransformer(): DataTransformerInterface
    {
        if ($this->transformer instanceof DataTransformerInterface) {
            return $this->transformer;
        }

        $configured = Configure::read('FileStorage.behaviorConfig.dataTransformer');
        $this->transformer = $configured instanceof DataTransformerInterface
            ? $configured
            : new DataTransformer($this->storageTable);

        return $this->transformer;
    }
}
