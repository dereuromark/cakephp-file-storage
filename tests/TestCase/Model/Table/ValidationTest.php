<?php

declare(strict_types = 1);

namespace FileStorage\Test\TestCase\Model\Table;

use Cake\Core\Configure;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;
use TestApp\Storage\Validation\ImageValidator;

class ValidationTest extends FileStorageTestCase
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
    protected function prepareDependencies(): void
    {
        parent::prepareDependencies();

        $behaviorConfig = Configure::read('FileStorage.behaviorConfig');
        $behaviorConfig['fileValidator'] = ImageValidator::class;
        Configure::write('FileStorage.behaviorConfig', $behaviorConfig);
    }

    /**
     * @return void
     */
    public function testUploadValidateImage(): void
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
        $expected = [
            'avatar' => [
                'file' => [
                    'fileBelowMaxWidth' => 'This image should at max 400px wide',
                    'customName' => 'yourErrorMessage',
                ],
            ],
        ];
        $this->assertSame($expected, $entity->getErrors());
    }
}
