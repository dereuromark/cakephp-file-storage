<?php
use Cake\Core\Configure;
use Migrations\BaseMigration;

class InitialMigration extends BaseMigration {

/**
 * Migrate Up.
 */
	public function up() {
		// user_id and foreign_key reference integer primary keys (the app's users
		// and any host record), so they follow the application's primary-key
		// signedness. The flag is false (signed) when unset, so an unset flag yields
		// signed columns matching the default-signed ids they reference. The id stays
		// a char(36) UUID (the storage library's file identity). Unsigned only on MySQL.
		$signed = !(bool)Configure::read('Migrations.unsigned_primary_keys', false);

		$this->table('file_storage', ['id' => false, 'primary_key' => 'id'])
			->addColumn('id', 'char', ['limit' => 36])
			->addColumn('user_id', 'biginteger', ['null' => true, 'default' => null, 'signed' => $signed])
			->addColumn('foreign_key', 'biginteger', ['null' => true, 'default' => null, 'signed' => $signed])
			->addColumn('model', 'string', ['limit' => 128, 'null' => true, 'default' => null])
			->addColumn('filename', 'string', ['limit' => 255, 'null' => true, 'default' => null])
			->addColumn('filesize', 'integer', ['limit' => 16, 'null' => true, 'default' => null])
			->addColumn('mime_type', 'string', ['limit' => 32, 'null' => true, 'default' => null])
			->addColumn('extension', 'string', ['limit' => 5, 'null' => true, 'default' => null])
			->addColumn('hash', 'string', ['limit' => 64, 'null' => true, 'default' => null])
			->addColumn('path', 'string', ['null' => true, 'default' => null])
			->addColumn('adapter', 'string', ['limit' => 32, 'null' => true, 'default' => null])
			->addColumn('created', 'datetime', ['null' => true, 'default' => null])
			->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
			->create();
	}

/**
 * Migrate Down.
 */
	public function down() {
		$this->dropTable('file_storage');
	}
}
