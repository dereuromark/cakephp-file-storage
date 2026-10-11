<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Behavior;

use ArrayObject;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use FileStorage\Test\TestCase\FileStorageTestCase;
use FileStorage\Utility\StorageUtils;

class FileAssociationBehaviorTest extends FileStorageTestCase
{
    protected array $fixtures = [
        'plugin.FileStorage.Items',
        'plugin.FileStorage.FileStorage',
    ];

    public function testCustomCollectionsAndMultipleAssociationsPersistUploads(): void
    {
        $items = $this->getTableLocator()->get('Items', ['className' => Table::class]);
        $items->hasOne('CoverImages', [
            'className' => 'FileStorage.FileStorage',
            'foreignKey' => 'foreign_key',
            'conditions' => ['CoverImages.model' => 'Items', 'CoverImages.collection' => 'Cover'],
        ]);
        $items->hasMany('GalleryImages', [
            'className' => 'FileStorage.FileStorage',
            'foreignKey' => 'foreign_key',
            'conditions' => ['GalleryImages.model' => 'Items', 'GalleryImages.collection' => 'Gallery'],
        ]);
        $items->addBehavior('FileStorage.FileAssociation', [
            'associations' => [
                'CoverImages' => ['collection' => 'Cover'],
                'GalleryImages' => ['collection' => 'Gallery'],
            ],
        ]);
        $items->CoverImages->getBehavior('FileStorage')->setConfig('fileProcessor', null);
        $items->GalleryImages->getBehavior('FileStorage')->setConfig('fileProcessor', null);
        $item = $items->newEntity([
            'name' => 'With images',
            'cover_image' => ['file' => StorageUtils::fileToUploadedFileObject($this->fileFixtures . 'titus.jpg')],
            'gallery_images' => [
                ['file' => StorageUtils::fileToUploadedFileObject($this->fileFixtures . 'demo.png')],
                ['file' => StorageUtils::fileToUploadedFileObject($this->fileFixtures . 'cake.icon.png')],
            ],
        ]);
        $items->saveOrFail($item);
        $loaded = $items->get($item->id, contain: ['CoverImages', 'GalleryImages']);
        $this->assertSame('Cover', $loaded->cover_image->collection);
        $this->assertSame('titus.jpg', $loaded->cover_image->filename);
        $this->assertNotEmpty($loaded->cover_image->uuid);
        $this->assertSame([], $loaded->cover_image->variants);
        $this->assertSame($item->id, $loaded->cover_image->foreign_key);
        $this->assertFileExists($this->testPath . $loaded->cover_image->path);
        $this->assertCount(2, $loaded->gallery_images);
        foreach ($loaded->gallery_images as $image) {
            $this->assertSame('Gallery', $image->collection);
            $this->assertSame($item->id, $image->foreign_key);
            $this->assertFileExists($this->testPath . $image->path);
        }
    }

    public function testReplacementRunsForEveryHasOneAssociation(): void
    {
        $items = $this->getTableLocator()->get('Items', ['className' => Table::class]);
        foreach (['Covers' => 'Cover', 'Avatars' => 'Avatar'] as $alias => $collection) {
            $items->hasOne($alias, [
                'className' => 'FileStorage.FileStorage',
                'foreignKey' => 'foreign_key',
                'conditions' => [$alias . '.model' => 'Items', $alias . '.collection' => $collection],
            ]);
        }
        $items->addBehavior('FileStorage.FileAssociation', [
            'associations' => [
                'Covers' => ['collection' => 'Cover'],
                'Avatars' => ['collection' => 'Avatar'],
            ],
        ]);
        $uploads = [];
        foreach (['cover' => 'Cover', 'avatar' => 'Avatar'] as $property => $collection) {
            $previous = $this->FileStorage->newEntity([
                'model' => 'Items',
                'collection' => $collection,
                'foreign_key' => 1,
                'file' => StorageUtils::fileToUploadedFileObject($this->fileFixtures . 'titus.jpg', 'image/jpeg'),
            ]);
            $this->FileStorage->saveOrFail($previous);
            $uploads[$property] = $this->FileStorage->newEntity([
                'model' => 'Items',
                'collection' => $collection,
                'file' => StorageUtils::fileToUploadedFileObject($this->fileFixtures . 'demo.png', 'image/png'),
            ]);
        }
        $entity = new Entity(['id' => 1] + $uploads);
        $items->getBehavior('FileAssociation')->afterSave(new Event('Model.afterSave', $items), $entity, new ArrayObject());
        foreach (['cover' => 'Cover', 'avatar' => 'Avatar'] as $property => $collection) {
            $rows = $this->FileStorage->find()->where([
                'model' => 'Items',
                'collection' => $collection,
                'foreign_key' => 1,
            ])->all()->toArray();
            $this->assertCount(1, $rows);
            $this->assertSame('demo.png', $rows[0]->filename);
            $this->assertSame($entity->{$property}->id, $rows[0]->id);
        }
    }
}
