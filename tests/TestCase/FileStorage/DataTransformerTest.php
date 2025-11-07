<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\FileStorage;

use Cake\TestSuite\TestCase;
use FileStorage\FileStorage\DataTransformer;
use FileStorage\Model\Entity\FileStorage;
use PhpCollective\Infrastructure\Storage\File;

class DataTransformerTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.FileStorage.FileStorage',
    ];

    /**
     * @return void
     */
    public function testEntityToFileObject(): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $transformer = new DataTransformer($table);

        $entity = $table->newEntity([
            'filename' => 'test.jpg',
            'filesize' => 12345,
            'mime_type' => 'image/jpeg',
            'adapter' => 'Local',
            'collection' => 'Photos',
            'model' => 'Item',
            'foreign_key' => '99',
            'metadata' => ['width' => 800],
            'variants' => ['thumb' => ['path' => 'thumb.jpg']],
            'path' => 'Item/test.jpg',
        ]);
        $entity->id = 'test-uuid-123'; // Set after creation to avoid auto-generation

        $file = $transformer->entityToFileObject($entity);

        $this->assertSame('test.jpg', $file->filename());
        $this->assertSame(12345, $file->filesize());
        $this->assertSame('image/jpeg', $file->mimeType());
        $this->assertSame('Local', $file->storage());
        $this->assertSame('Photos', $file->collection());
        $this->assertSame('Item', $file->model());
        $this->assertSame('99', $file->modelId());
        $this->assertSame(['width' => 800], $file->metadata());
        $this->assertSame(['thumb' => ['path' => 'thumb.jpg']], $file->variants());
        $this->assertSame('test-uuid-123', $file->uuid());
        $this->assertSame('Item/test.jpg', $file->path());
    }

    /**
     * @return void
     */
    public function testFileObjectToEntityNew(): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $transformer = new DataTransformer($table);

        $file = File::create(
            'new-file.png',
            54321,
            'image/png',
            'S3',
            'Gallery',
            'Post',
            '42',
            ['height' => 600],
            ['large' => ['path' => 'large.png']],
        );
        $file = $file->withUuid('new-uuid-456')
            ->withPath('Post/new-file.png');

        $entity = $transformer->fileObjectToEntity($file, null);

        $this->assertInstanceOf(FileStorage::class, $entity);
        $this->assertSame('new-uuid-456', $entity->id);
        $this->assertSame('new-file.png', $entity->filename);
        $this->assertSame(54321, $entity->filesize);
        $this->assertSame('image/png', $entity->mime_type);
        $this->assertSame('S3', $entity->adapter);
        $this->assertSame('Gallery', $entity->collection);
        $this->assertSame('Post', $entity->model);
        $this->assertSame(42, $entity->foreign_key);
        $this->assertSame(['height' => 600], $entity->metadata);
        $this->assertSame(['large' => ['path' => 'large.png']], $entity->variants);
        $this->assertSame('Post/new-file.png', $entity->path);
    }

    /**
     * @return void
     */
    public function testFileObjectToEntityUpdate(): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $transformer = new DataTransformer($table);

        $existing = $table->get(1);
        $this->assertSame('cake.icon.png', $existing->filename);

        $file = File::create(
            'updated.png',
            99999,
            'image/png',
            'Local',
            'Photos',
            'Item',
            '1',
            [],
            [],
        );
        $file = $file->withUuid('1')
            ->withPath('Item/updated.png');

        $entity = $transformer->fileObjectToEntity($file, $existing);

        $this->assertSame($existing, $entity);
        $this->assertSame('updated.png', $entity->filename);
        $this->assertSame(99999, $entity->filesize);
        $this->assertSame('Item/updated.png', $entity->path);
    }
}
