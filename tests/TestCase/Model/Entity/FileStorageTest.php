<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Entity;

use FileStorage\Model\Entity\FileStorage;
use FileStorage\Test\TestCase\FileStorageTestCase;

class FileStorageTest extends FileStorageTestCase
{
    /**
     * @return void
     */
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
     * The column is json, but nothing guarantees the value arrives decoded: with
     * a text column and no select type map entry the raw JSON string comes
     * through. Casting that with (array) yields ['{"...}'], not the decoded map,
     * so the accessors would silently return null.
     *
     * @return void
     */
    public function testVariantsAccessorsDecodeJsonString(): void
    {
        $fileStorage = new FileStorage();
        $fileStorage->set('variants', '{"t150":{"path":"test\\/path.jpg","url":"http:\\/\\/example.com\\/t150.jpg"}}');

        $this->assertSame(['t150'], array_keys($fileStorage->variants()));
        $this->assertSame('http://example.com/t150.jpg', $fileStorage->getVariantUrl('t150'));
        $this->assertSame('test/path.jpg', $fileStorage->getVariantPath('t150'));
    }

    /**
     * @return void
     */
    public function testVariantsFallsBackToEmptyArray(): void
    {
        $fileStorage = new FileStorage();
        $this->assertSame([], $fileStorage->variants());

        $fileStorage->set('variants', 'not json at all');
        $this->assertSame([], $fileStorage->variants());

        $fileStorage->set('variants', ['t150' => ['url' => 'x']]);
        $this->assertSame(['t150' => ['url' => 'x']], $fileStorage->variants());
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

    /**
     * @return void
     */
    public function testPublicId(): void
    {
        $fileStorage = new FileStorage([
            'uuid' => '10000000-0000-4000-8000-000000000001',
        ], [
            'accessibleFields' => ['*' => true],
        ]);

        $this->assertSame('10000000-0000-4000-8000-000000000001', $fileStorage->publicId());
    }
}
