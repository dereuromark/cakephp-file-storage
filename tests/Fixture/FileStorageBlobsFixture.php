<?php declare(strict_types=1);

namespace FileStorage\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Blob registry fixture.
 *
 * @author Mark Scherer
 * @license MIT
 */
class FileStorageBlobsFixture extends TestFixture
{
    /**
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer', 'autoIncrement' => true],
        'adapter' => ['type' => 'string', 'length' => 32, 'null' => false],
        'hash' => ['type' => 'string', 'length' => 64, 'null' => false],
        'path' => ['type' => 'string', 'length' => 255, 'null' => true],
        'touched' => ['type' => 'datetime', 'null' => false],
        'created' => ['type' => 'datetime', 'null' => true],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'adapter_hash' => ['type' => 'unique', 'columns' => ['adapter', 'hash']],
        ],
    ];

    public array $records = [];
}
