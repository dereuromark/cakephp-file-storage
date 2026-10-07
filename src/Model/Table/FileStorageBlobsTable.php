<?php declare(strict_types=1);

namespace FileStorage\Model\Table;

use Cake\ORM\Table;

/**
 * Stores content-addressed blob claims.
 *
 * @author Mark Scherer
 * @license MIT
 */
class FileStorageBlobsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('file_storage_blobs');
        $this->setPrimaryKey('id');
    }
}
