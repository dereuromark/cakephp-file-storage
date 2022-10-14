<?php

declare(strict_types = 1);

namespace Burzum\FileStorage\Model\Table;

use Cake\Core\Configure;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\ORM\Table;

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
 *
 * @method \Burzum\FileStorage\Model\Entity\FileStorage newEmptyEntity()
 * @method \Burzum\FileStorage\Model\Entity\FileStorage newEntity(array $data, array $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage[] newEntities(array $data, array $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage get($primaryKey, $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \Burzum\FileStorage\Model\Entity\FileStorage[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Burzum\FileStorage\Model\Behavior\FileStorageBehavior
 */
class FileStorageTable extends Table
{
    /**
     * @inheritDoc
     */
    public function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
    {
        $schema->addColumn('variants', 'json');
        $schema->addColumn('metadata', 'json');

        return parent::_initializeSchema($schema);
    }

    /**
     * Initialize
     *
     * @param array $config
     *
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('file_storage');
        $this->setPrimaryKey('id');
        $this->setDisplayField('filename');

        $this->addBehavior('Timestamp');
        $this->addBehavior(
            'Burzum/FileStorage.FileStorage',
            (array)Configure::read('FileStorage.behaviorConfig'),
        );
    }
}
