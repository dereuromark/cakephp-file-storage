<?php
declare(strict_types = 1);

namespace FileStorage\Test\TestCase\Model\Entity;

use FileStorage\Model\Entity\FileStorage;
use FileStorage\Test\TestCase\FileStorageTestCase;

class FileStorageTest extends FileStorageTestCase
{
    public function testNew(): void
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                't150' => [
                    'path' => 'test/path/testimage.c3f33c2a.jpg',
                    'url' => '',
                ],
            ],
            'metadata' => [
                'foo' => 'bar',
            ],
        ]);

        $this->assertNotEmpty($image->variants);
        $this->assertNotEmpty($image->metadata);
    }

    /**
     * @return void
     */
    public function testGetVariantUrl(): void
    {
        $fileStorage = new FileStorage();

        $result = $fileStorage->getVariantUrl('nonexistent');
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testGetVariantPath(): void
    {
        $fileStorage = new FileStorage();

        $result = $fileStorage->getVariantPath('nonexistent');
        $this->assertNull($result);
    }
}
