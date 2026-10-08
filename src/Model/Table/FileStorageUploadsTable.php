<?php declare(strict_types=1);

namespace FileStorage\Model\Table;

use Cake\ORM\Table;

/**
 * Resumable upload sessions.
 *
 * @author Mark Scherer
 * @license MIT
 */
class FileStorageUploadsTable extends Table
{
    /**
     * @param array<string, mixed> $config
     *
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('file_storage_uploads');
        $this->setPrimaryKey('id');
    }
}
