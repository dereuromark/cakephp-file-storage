<?php declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Creates resumable upload sessions.
 *
 * @author Mark Scherer
 * @license MIT
 */
class CreateFileStorageUploads extends BaseMigration
{
    public function up(): void
    {
        $this->table('file_storage_uploads', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('model', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('collection', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('filename', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('size', 'biginteger', ['null' => false])
            ->addColumn('upload_offset', 'biginteger', ['null' => false])
            ->addColumn('hash', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('declared_hash', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('hash_state', 'text', ['null' => true])
            ->addColumn('metadata', 'text', ['null' => true])
            ->addColumn('state', 'string', ['limit' => 16, 'null' => false])
            ->addColumn('file_storage_id', 'integer', ['null' => true])
            ->addColumn('expires', 'datetime', ['null' => false])
            ->addColumn('revision', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['user_id'])
            ->addIndex(['expires'])
            ->create();
    }

    public function down(): void
    {
        $this->table('file_storage_uploads')->drop()->save();
    }
}
