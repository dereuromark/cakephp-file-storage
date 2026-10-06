<?php declare(strict_types=1);

namespace FileStorage\FileStorage;

use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;

/**
 * Regenerates the variants of a stored file and writes back only the variant
 * map. Saving the whole entity here would undo a file replacement that
 * commits while the processor runs.
 *
 * @author Mark Scherer
 * @license MIT
 */
class VariantRegenerator
{
    use EventDispatcherTrait;

    public function __construct(
        protected Table $table,
        protected DataTransformerInterface $transformer,
        protected ProcessorInterface $processor,
    ) {
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $operations
     * @param bool $merge
     *
     * @return void
     */
    public function regenerate(EntityInterface $entity, array $operations, bool $merge): void
    {
        $variants = $this->table->getConnection()->transactional(function () use ($entity, $operations, $merge): ?array {
            $primaryKey = [];
            foreach ((array)$this->table->getPrimaryKey() as $column) {
                $primaryKey[$column] = $entity->get($column);
            }
            $query = $this->table->find()->where($primaryKey);
            // Holds off a concurrent replacement until the variants are written.
            // SQLite has no row locks; the source check below is all it gets.
            $driver = $this->table->getConnection()->getDriver();
            if ($driver instanceof Mysql || $driver instanceof Postgres) {
                $query->epilog('FOR UPDATE');
            }
            $fresh = $query->first();
            if ($fresh === null) {
                return null;
            }

            // What the variants were generated from. A row that points somewhere
            // else by the time of the update keeps its own variants.
            $source = ['path' => $fresh->get('path')];
            if ($this->table->getSchema()->hasColumn('adapter')) {
                $source['adapter'] = $fresh->get('adapter');
            }
            $file = $this->transformer->entityToFileObject($fresh);
            if ($operations !== []) {
                $file = $file->withVariants($operations, $merge);
            }
            $this->dispatchEvent('FileStorage.beforeFileProcessing', [
                'entity' => $fresh,
                'file' => $file,
            ], $this->table);
            $file = $this->processor->process($file);
            $this->dispatchEvent('FileStorage.afterFileProcessing', [
                'entity' => $fresh,
                'file' => $file,
            ], $this->table);

            $fields = ['variants' => $file->variants()];
            if ($this->table->getSchema()->hasColumn('modified')) {
                $fields['modified'] = DateTime::now();
            }
            $conditions = $primaryKey;
            foreach ($source as $column => $value) {
                $conditions[$column . ' IS'] = $value;
            }
            $this->table->updateAll($fields, $conditions);

            // Read back what the row holds now. The affected-row count cannot
            // tell a skipped update from one that changed nothing on MySQL.
            $persisted = $this->table->find()->select(['variants'])->where($primaryKey)->first();

            return $persisted?->get('variants');
        });
        if ($variants !== null) {
            $entity->set('variants', $variants);
            $entity->setDirty('variants', false);
        }
    }
}
