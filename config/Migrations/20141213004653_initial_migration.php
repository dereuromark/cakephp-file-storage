<?php
use Cake\Core\Configure;
use Migrations\BaseMigration;

class InitialMigration extends BaseMigration {

/**
 * Migrate Up.
 */
	public function up() {
		$type = (string)Configure::read('Polymorphic.type', 'integer');
		$signed = !(bool)Configure::read('Migrations.unsigned_primary_keys', false);

		$polymorphicOptions = [
			'null' => true,
			'default' => null,
		];
		if (in_array($type, ['integer', 'biginteger'], true)) {
			$polymorphicOptions['signed'] = $signed;
		}

		$this->table('file_storage', ['id' => false, 'primary_key' => 'id'])
			->addColumn('id', 'char', ['limit' => 36])
			->addColumn('user_id', 'integer', ['null' => true, 'default' => null, 'signed' => $signed])
			->addColumn('foreign_key', $type, $polymorphicOptions)
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
