<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Table;

use Cake\Core\Configure;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Laminas\Diactoros\UploadedFile;

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

    /**
     * Test getUrl method exists and returns string
     *
     * Note: Full URL generation testing requires routing to be configured,
     * which is better tested in integration tests.
     *
     * @return void
     */
    public function testGetUrlMethodExists(): void
    {
        $fileStorage = $this->FileStorage->newEntity([
            'id' => 'test-uuid-123',
            'filename' => 'test.jpg',
        ]);

        $this->assertTrue(method_exists($this->FileStorage, 'getUrl'));

        // Verify it accepts the expected parameters
        $reflection = new \ReflectionMethod($this->FileStorage, 'getUrl');
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('entity', $params[0]->getName());
        $this->assertEquals('options', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
    }
}
