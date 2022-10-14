<?php

declare(strict_types = 1);

namespace FileStorage\Test\TestCase;

use Cake\Core\Configure;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\ORM\Table;
use FileStorage\Model\Entity\FileStorage;

/**
 * FileStorageTestTable
 */
class FileStorageTestTable extends Table
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
        $this->setAlias('FileStorage');
        $this->setEntityClass(FileStorage::class);
        $this->setDisplayField('filename');

        $this->addBehavior(
            'FileStorage.FileStorage',
            Configure::read('FileStorage.behaviorConfig'),
        );
    }
}
