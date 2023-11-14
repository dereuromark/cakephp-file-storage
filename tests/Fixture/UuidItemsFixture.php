<?php
declare(strict_types = 1);

namespace FileStorage\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UuidItemsFixture extends TestFixture
{
    /**
     * Name
     *
     * @var string
     */
    public $name = 'Items';

    /**
     * Table
     *
     * @var string
     */
    public string $table = 'items';

    /**
     * Fields
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'uuid', 'null' => true, 'default' => null, 'length' => 36],
        'name' => ['type' => 'string', 'null' => true, 'default' => null],
        'content' => ['type' => 'string', 'null' => true, 'default' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    /**
     * Records
     *
     * @var array
     */
    public array $records = [
        [
            'id' => 'item-1',
            'name' => 'Cake',
        ],
        [
            'id' => 'item-2',
            'name' => 'More Cake',
        ],
        [
            'id' => 'item-3',
            'name' => 'A lot Cake',
        ],
    ];
}
