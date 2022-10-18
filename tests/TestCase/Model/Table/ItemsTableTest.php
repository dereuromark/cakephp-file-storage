<?php

declare(strict_types = 1);

namespace FileStorage\Test\TestCase\Model\Table;

use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;

class ItemsTableTest extends FileStorageTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'plugin.FileStorage.Items',
        'plugin.FileStorage.FileStorage',
    ];

    /**
     * @var \Cake\ORM\Table
     */
    protected $table;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->table = $this->getTableLocator()->get('Items');

        $this->table->hasOne('Avatars', [
            'className' => 'FileStorage.FileStorage',
            'foreignKey' => 'foreign_key',
            'conditions' => [
                'Avatars.model' => 'Items',
            ],
            'joinType' => 'LEFT',
        ]);

        $this->table->hasMany('Photos', [
            'className' => 'FileStorage.FileStorage',
            'foreignKey' => 'foreign_key',
            'conditions' => [
                'Photos.model' => 'Items',
            ],
            'joinType' => 'LEFT',
        ]);

        $this->table->addBehavior(
            'FileStorage.FileAssociation',
            [
                'associations' => [
                    'Avatars' => [
                        'collection' => 'Avatars',
                        'replace' => true,
                    ],
                    'Photos' => [
                        'collection' => 'Photos',
                    ],
                ],
            ],
        );
    }

    /**
     * endTest
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->FileStorage, $this->table);
        $this->getTableLocator()->clear();
    }

    /**
     * @return void
     */
    public function testUploadNew()
    {
        $entity = $this->table->newEntity([
            'name' => 'Test',
            'avatar' => [
                'file' => new UploadedFile(
                    $this->fileFixtures . 'titus.jpg',
                    filesize($this->fileFixtures . 'titus.jpg'),
                    UPLOAD_ERR_OK,
                    'tituts.jpg',
                    'image/jpeg',
                ),
            ],
        ]);
        $this->assertSame([], $entity->getErrors());

        $this->table->saveOrFail($entity);

        $entity = $this->table->get($entity->id, ['contain' => 'Avatars']);

        $this->assertNotEmpty($entity->avatar);

        $this->assertSame('Items', $entity->avatar->model);
        $this->assertNotEmpty($entity->avatar->foreign_key);
        $this->assertSame('Avatars', $entity->avatar->collection);
        $this->assertNotEmpty($entity->avatar->metadata);
        $this->assertNotEmpty($entity->avatar->variants);
        $this->assertStringStartsWith('Items/Avatars/', $entity->avatar->path);
    }

    /**
     * @return void
     */
    public function testUploadOverwriteExisting()
    {
        // Upload first pic
        $entity = $this->table->newEntity([
            'name' => 'Test',
            'avatar' => [
                'file' => new UploadedFile(
                    $this->fileFixtures . 'titus.jpg',
                    filesize($this->fileFixtures . 'titus.jpg'),
                    UPLOAD_ERR_OK,
                    'tituts.jpg',
                    'image/jpeg',
                ),
            ],
        ]);
        $this->assertSame([], $entity->getErrors());

        $this->table->saveOrFail($entity);

        $entity = $this->table->get($entity->id, ['contain' => 'Avatars']);

        $this->assertNotEmpty($entity->avatar);
        $this->assertStringStartsWith('Items/Avatars/', $entity->avatar->path);

        $expected = [
            'width' => 512,
            'height' => 768,
        ];
        $this->assertSame($expected, $entity->avatar->metadata);

        // Upload second pic
        $entity = $this->table->patchEntity($entity, [
            'avatar' => [
                'file' => new UploadedFile(
                    $this->fileFixtures . 'demo.png',
                    filesize($this->fileFixtures . 'demo.png'),
                    UPLOAD_ERR_OK,
                    'demo.png',
                    'image/png',
                ),
            ],
        ]);
        $this->assertSame([], $entity->getErrors());

        $this->table->saveOrFail($entity);

        $entity = $this->table->get($entity->id, ['contain' => 'Avatars']);

        $this->assertNotEmpty($entity->avatar);

        $this->assertSame('Items', $entity->avatar->model);
        $this->assertNotEmpty($entity->avatar->foreign_key);
        $this->assertSame('Avatars', $entity->avatar->collection);
        $this->assertNotEmpty($entity->avatar->metadata);
        $this->assertNotEmpty($entity->avatar->variants);
        $this->assertStringStartsWith('Items/Avatars/', $entity->avatar->path);

        debug($entity);
        $expected = [
            'width' => 512,
            'height' => 512, // !!!
        ];
        $this->assertSame($expected, $entity->avatar->metadata);
    }
}
