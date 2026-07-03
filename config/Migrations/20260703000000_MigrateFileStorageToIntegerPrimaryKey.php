<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Migrations\BaseMigration;
use Migrations\Migration\IrreversibleMigrationException;

class MigrateFileStorageToIntegerPrimaryKey extends BaseMigration
{
    /**
     * @throws \RuntimeException
     *
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('file_storage')) {
            return;
        }

        if ($this->getAdapter()->hasColumn('file_storage', 'uuid')) {
            return;
        }

        if ($this->hasTable('file_storage_uuid_id_backup')) {
            throw new RuntimeException('Cannot migrate file_storage: file_storage_uuid_id_backup already exists.');
        }

        $rows = $this->fetchAll('SELECT * FROM file_storage');

        $this->table('file_storage')
            ->rename('file_storage_uuid_id_backup')
            ->update();

        $this->createFileStorageTable();

        if (!$rows) {
            return;
        }

        $newRows = [];
        foreach ($rows as $row) {
            $newRows[] = [
                'uuid' => $row['id'],
                'user_id' => $row['user_id'] ?? null,
                'foreign_key' => $row['foreign_key'] ?? null,
                'model' => $row['model'] ?? null,
                'collection' => $row['collection'] ?? null,
                'filename' => $row['filename'] ?? null,
                'filesize' => $row['filesize'] ?? null,
                'mime_type' => $row['mime_type'] ?? null,
                'extension' => $row['extension'] ?? null,
                'hash' => $row['hash'] ?? null,
                'path' => $row['path'] ?? null,
                'adapter' => $row['adapter'] ?? null,
                'variants' => $row['variants'] ?? null,
                'metadata' => $row['metadata'] ?? null,
                'created' => $row['created'] ?? null,
                'modified' => $row['modified'] ?? null,
            ];
        }

        $this->table('file_storage')
            ->insert($newRows)
            ->saveData();
    }

    /**
     * @throws \Migrations\Migration\IrreversibleMigrationException
     *
     * @return void
     */
    public function down(): void
    {
        throw new IrreversibleMigrationException(
            'Restore file_storage_uuid_id_backup manually if you need to roll back this major-version migration.',
        );
    }

    /**
     * @return void
     */
    protected function createFileStorageTable(): void
    {
        $type = (string)Configure::read('Polymorphic.type', 'integer');
        $signed = !(bool)Configure::read('Migrations.unsigned_primary_keys', false);

        $polymorphicOptions = [
            'null' => true,
            'default' => null,
        ];
        if (in_array($type, ['integer', 'biginteger'], true)) {
            $polymorphicOptions['signed'] = $signed;
        }

        $this->table('file_storage')
            ->addColumn('uuid', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null, 'signed' => $signed])
            ->addColumn('foreign_key', $type, $polymorphicOptions)
            ->addColumn('model', 'string', ['limit' => 128, 'null' => true, 'default' => null])
            ->addColumn('collection', 'string', ['limit' => 128, 'null' => true, 'default' => null])
            ->addColumn('filename', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('filesize', 'integer', ['limit' => 16, 'null' => true, 'default' => null])
            ->addColumn('mime_type', 'string', ['limit' => 128, 'null' => true, 'default' => null])
            ->addColumn('extension', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('hash', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addColumn('path', 'string', ['null' => true, 'default' => null])
            ->addColumn('adapter', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('variants', 'json', ['null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['uuid'], ['unique' => true])
            ->create();
    }
}
