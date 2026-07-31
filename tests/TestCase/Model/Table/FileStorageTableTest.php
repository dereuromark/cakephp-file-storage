<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Table;

use FileStorage\Model\Entity\FileStorage;
use FileStorage\Test\Fixture\FileStorageFixture;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;
use TestApp\Model\Table\AppFilesTable;

/**
 * File Storage Test
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 */
class FileStorageTableTest extends FileStorageTestCase
{
    /**
     * endTest
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->FileStorage);
        $this->getTableLocator()->clear();
    }

    /**
     * testInitialization
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->assertSame('file_storage', $this->FileStorage->getTable());
        $this->assertSame('filename', $this->FileStorage->getDisplayField());
    }

    /**
     * @return void
     */
    public function testGetByUuid(): void
    {
        $entity = $this->FileStorage->getByUuid('10000000-0000-4000-8000-000000000001');

        $this->assertNotNull($entity);
        $this->assertSame(1, $entity->id);
    }

    /**
     * An application subclassing the table must keep the plugin entity.
     *
     * Cake resolves the entity class from the table class name, so a subclass in
     * the application namespace resolves to a non-existent entity there and
     * silently falls back to Cake\ORM\Entity. Typed return values such as
     * getByUuid(): ?FileStorage then fail with a TypeError.
     *
     * @return void
     */
    public function testSubclassKeepsPluginEntityClass(): void
    {
        $table = $this->getTableLocator()->get('AppFiles', [
            'className' => AppFilesTable::class,
        ]);

        $this->assertSame(FileStorage::class, $table->getEntityClass());
        $this->assertInstanceOf(FileStorage::class, $table->getByUuid('10000000-0000-4000-8000-000000000001'));
    }

    /**
     * The fixture must be typed by the plugin table, not by whatever the
     * application happens to have under the unprefixed `FileStorage` alias.
     *
     * Cake takes the insert types of a fixture from the ORM table its alias
     * resolves to. With the unprefixed alias that is the generic Cake\ORM\Table
     * fallback, whose reflected `variants` column is plain text, so a record
     * value is stored unencoded and an array value cannot be stored at all.
     *
     * @return void
     */
    public function testFixtureSchemaUsesPluginColumnTypes(): void
    {
        $schema = (new FileStorageFixture())->getTableSchema();

        $this->assertSame('json', $schema->getColumnType('variants'));
        $this->assertSame('json', $schema->getColumnType('metadata'));
    }

    /**
     * The json columns must come back as arrays.
     *
     * They are decoded exactly once on read, so a value that was encoded twice
     * on write arrives as the raw JSON string and every variant lookup misses.
     *
     * @return void
     */
    public function testJsonColumnsReadBackAsArrays(): void
    {
        $entity = $this->FileStorage->get(1);

        $this->assertIsArray($entity->get('variants'));
        $this->assertIsArray($entity->get('metadata'));
    }

    /**
     * Testing a complete save call
     *
     * @link https://github.com/burzum/cakephp-file-storage/issues/85
     *
     * @return void
     */
    public function testFileSaving()
    {
        $entity = $this->FileStorage->newEntity([
            'model' => 'Document',
            'adapter' => 'Local',
            'file' => new UploadedFile(
                $this->fileFixtures . 'titus.jpg',
                filesize($this->fileFixtures . 'titus.jpg'),
                UPLOAD_ERR_OK,
                'tituts.jpg',
                'image/jpeg',
            ),
        ]);
        $this->assertSame([], $entity->getErrors());

        $this->FileStorage->saveOrFail($entity);
    }

    /**
     * Testing a complete save call
     *
     * @link https://github.com/burzum/cakephp-file-storage/issues/85
     *
     * @return void
     */
    public function testFileSavingArray()
    {
        $entity = $this->FileStorage->newEntity([
            'model' => 'Document',
            'adapter' => 'Local',
            'file' => [
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($this->fileFixtures . 'titus.jpg'),
                'type' => 'image/jpeg',
                'name' => 'tituts.jpg',
                'tmp_name' => $this->fileFixtures . 'titus.jpg',
            ],
        ]);
        $this->assertSame([], $entity->getErrors());

        $this->FileStorage->saveOrFail($entity);
    }
}
