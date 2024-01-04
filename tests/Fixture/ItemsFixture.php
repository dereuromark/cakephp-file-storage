<?php declare(strict_types=1);

namespace FileStorage\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ItemsFixture extends TestFixture
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
        'id' => ['type' => 'integer', 'autoIncrement' => true],
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
            'name' => 'Cake',
        ],
        [
            'name' => 'More Cake',
        ],
        [
            'name' => 'A lot Cake',
        ],
    ];
}
