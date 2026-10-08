<?php

declare(strict_types=1);

use Cake\Utility\Inflector;

$tables = [];

/**
 * @var \DirectoryIterator<\DirectoryIterator> $iterator
 */
$iterator = new DirectoryIterator(__DIR__ . DS . 'Fixture');
foreach ($iterator as $file) {
    if (!preg_match('/(\w+)Fixture.php$/', (string)$file, $matches)) {
        continue;
    }

    $name = $matches[1];
    $tableName = null;
    $class = 'FileStorage\\Test\\Fixture\\' . $name . 'Fixture';
    try {
        $fieldsObject = (new ReflectionClass($class))->getProperty('fields');
        $tableObject = (new ReflectionClass($class))->getProperty('table');
        $tableName = $tableObject->getDefaultValue();
    } catch (ReflectionException) {
        continue;
    }

    if (!$tableName) {
        $tableName = Inflector::underscore($name);
    }

    $array = $fieldsObject->getDefaultValue();
    $constraints = $array['_constraints'] ?? [];
    $indexes = $array['_indexes'] ?? [];
    unset($array['_constraints'], $array['_indexes'], $array['_options']);
    $table = [
        'table' => $tableName,
        'columns' => $array,
        'constraints' => $constraints,
        'indexes' => $indexes,
    ];
    $tables[$tableName] = $table;
}

$tables['file_storage_uploads'] = [
    'table' => 'file_storage_uploads',
    'columns' => [
        'id' => ['type' => 'string', 'length' => 36, 'null' => false],
        'user_id' => ['type' => 'string', 'length' => 36, 'null' => false],
        'model' => ['type' => 'string', 'length' => 255, 'null' => false],
        'collection' => ['type' => 'string', 'length' => 255, 'null' => true],
        'filename' => ['type' => 'string', 'length' => 255, 'null' => true],
        'size' => ['type' => 'biginteger', 'null' => false],
        'upload_offset' => ['type' => 'biginteger', 'null' => false],
        'hash' => ['type' => 'string', 'length' => 64, 'null' => true],
        'declared_hash' => ['type' => 'string', 'length' => 64, 'null' => true],
        'hash_state' => ['type' => 'text', 'null' => true],
        'metadata' => ['type' => 'text', 'null' => true],
        'state' => ['type' => 'string', 'length' => 16, 'null' => false],
        'file_storage_id' => ['type' => 'integer', 'null' => true],
        'expires' => ['type' => 'datetime', 'null' => false],
        'revision' => ['type' => 'integer', 'null' => false],
        'created' => ['type' => 'datetime', 'null' => false],
        'modified' => ['type' => 'datetime', 'null' => false],
    ],
    'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    'indexes' => [
        'uploads_owner' => ['type' => 'index', 'columns' => ['user_id']],
        'uploads_expires' => ['type' => 'index', 'columns' => ['expires']],
    ],
];

// Referenced tables must exist before PostgreSQL creates foreign keys.
$blobs = $tables['file_storage_blobs'];
unset($tables['file_storage_blobs']);

return ['file_storage_blobs' => $blobs] + $tables;
