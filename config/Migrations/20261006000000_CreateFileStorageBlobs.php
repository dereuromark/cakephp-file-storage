<?php declare(strict_types=1);

use Cake\Core\Configure;
use Migrations\BaseMigration;

/**
 * Creates the blob registry schema.
 *
 * @author Mark Scherer
 * @license MIT
 */
class CreateFileStorageBlobs extends BaseMigration
{
    public function up(): void
    {
        $signed = !(bool)Configure::read('Migrations.unsigned_primary_keys', false);
        $this->table('file_storage_blobs', ['signed' => $signed])
            ->addColumn('adapter', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('path', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('touched', 'datetime', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addIndex(['adapter', 'hash'], ['unique' => true])
            ->create();
        $this->table('file_storage')
            ->addColumn('blob_id', 'integer', ['null' => true, 'signed' => $signed])
            ->addIndex(['blob_id'])
            ->addIndex(['hash'])
            ->addForeignKey('blob_id', 'file_storage_blobs', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            ->update();
    }

    public function down(): void
    {
        $this->table('file_storage')
            ->dropForeignKey('blob_id')
            ->removeIndex(['blob_id'])
            ->removeIndex(['hash'])
            ->removeColumn('blob_id')
            ->update();
        $this->table('file_storage_blobs')->drop()->save();
    }
}
