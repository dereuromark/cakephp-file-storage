<?php declare(strict_types=1);

namespace FileStorage\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Table;
use FileStorage\Model\Entity\FileStorage;

/**
 * FileStorageTable
 *
 * Records in this table act as a reference to the real location of the stored
 * file data. All information of a row can be used to build a path to the file.
 * So the data in this table is pretty important.
 *
 * The reason for keeping all file references in this table is simply separation
 * of concerns: We separate the files from the other modules of the application
 * and threat them centralized and all the same.
 *
 * The actual storing and removing of the file data is handled by the Storage
 * Behavior that is attached to this table object.
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 * @extends \Cake\ORM\Table<array{FileStorage: \FileStorage\Model\Behavior\FileStorageBehavior, Timestamp: \Cake\ORM\Behavior\TimestampBehavior}, \FileStorage\Model\Entity\FileStorage>
 * @method \FileStorage\Model\Entity\FileStorage newEmptyEntity()
 * @method \FileStorage\Model\Entity\FileStorage newEntity(array $data, array $options = [])
 * @method array<\FileStorage\Model\Entity\FileStorage> newEntities(array $data, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \FileStorage\Model\Entity\FileStorage findOrCreate(\Cake\ORM\Query\SelectQuery|callable|array $search, ?callable $callback = null, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage patchEntity(\FileStorage\Model\Entity\FileStorage $entity, array $data, array $options = [])
 * @method array<\FileStorage\Model\Entity\FileStorage> patchEntities(iterable<\FileStorage\Model\Entity\FileStorage> $entities, array $data, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage|false save(\FileStorage\Model\Entity\FileStorage $entity, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage saveOrFail(\FileStorage\Model\Entity\FileStorage $entity, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage>|false saveMany(iterable<\FileStorage\Model\Entity\FileStorage> $entities, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> saveManyOrFail(iterable<\FileStorage\Model\Entity\FileStorage> $entities, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage>|false deleteMany(iterable<\FileStorage\Model\Entity\FileStorage> $entities, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> deleteManyOrFail(iterable<\FileStorage\Model\Entity\FileStorage> $entities, array $options = [])
 * @method bool delete(\FileStorage\Model\Entity\FileStorage $entity, array $options = [])
 * @method bool deleteOrFail(\FileStorage\Model\Entity\FileStorage $entity, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage|array<\FileStorage\Model\Entity\FileStorage> loadInto(\FileStorage\Model\Entity\FileStorage|array<\FileStorage\Model\Entity\FileStorage> $entities, array $contain)
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \FileStorage\Model\Behavior\FileStorageBehavior
 */
class FileStorageTable extends Table
{
    /**
     * Initialize
     *
     * @param array<string, mixed> $config
     *
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('file_storage');
        $this->setPrimaryKey('id');
        $this->setDisplayField('filename');

        $this->getSchema()
            ->addColumn('uuid', 'string')
            ->addColumn('variants', 'json')
            ->addColumn('metadata', 'json');

        $this->addBehavior('Timestamp');
        $this->addBehavior(
            'FileStorage.FileStorage',
            (array)Configure::read('FileStorage.behaviorConfig'),
        );
    }

    /**
     * Look up a file row by its public/storage UUID.
     *
     * @param string $uuid Public/storage UUID.
     *
     * @return \FileStorage\Model\Entity\FileStorage|null
     */
    public function getByUuid(string $uuid): ?FileStorage
    {
        /** @var \FileStorage\Model\Entity\FileStorage|null $entity */
        $entity = $this->find()
            ->where([$this->aliasField('uuid') => $uuid])
            ->first();

        return $entity;
    }
}
