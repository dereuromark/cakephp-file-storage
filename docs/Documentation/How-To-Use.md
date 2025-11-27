How to Use File Storage
=======================

This guide covers the essential concepts and usage patterns for the FileStorage plugin.

## Table of Contents

- [Basic Concepts](#basic-concepts)
- [Setting Up File Storage](#setting-up-file-storage)
- [Adding File Upload to Your Model](#adding-file-upload-to-your-model)
- [Uploading Files](#uploading-files)
- [Retrieving and Displaying Files](#retrieving-and-displaying-files)
- [Image Variants](#image-variants)
- [Deleting Files](#deleting-files)
- [Custom Storage Adapters](#custom-storage-adapters)

## Basic Concepts

### The File Storage Model

The FileStorage plugin uses a single table (`file_storage`) to track all uploaded files in your application. Each record contains:

- **id**: Unique UUID for the file
- **foreign_key**: The ID of the entity this file belongs to (e.g., user ID, post ID)
- **model**: The model name (e.g., 'User', 'Post')
- **collection**: The collection/type of file (e.g., 'Avatar', 'Cover', 'Gallery')
- **filename**: Original filename
- **filesize**: Size in bytes
- **mime_type**: File MIME type
- **extension**: File extension
- **path**: Storage path
- **adapter**: Storage adapter name (e.g., 'Local', 'S3')
- **variants**: JSON array of image variant information (for images)
- **metadata**: JSON array for additional metadata

### Model vs Collection

This is an important distinction:

- **Model**: The table alias (e.g., 'Users', 'Posts') - comes from `$this->table()->getAlias()`
- **Collection**: A grouping within a model (e.g., 'Avatar', 'Cover', 'Gallery')

For example, a Posts model might have:
- 'Cover' collection for cover images
- 'Gallery' collection for gallery images
- 'Attachments' collection for PDF attachments

## Setting Up File Storage

### 1. Install and Load Plugin

See the [Installation](Installation.md) guide.

### 2. Configure Storage Service

In your `config/bootstrap.php` or a dedicated `config/storage.php`:

```php
<?php
use PhpCollective\Infrastructure\Storage\StorageAdapterFactory;
use PhpCollective\Infrastructure\Storage\StorageService;
use PhpCollective\Infrastructure\Storage\Factories\LocalFactory;
use PhpCollective\Infrastructure\Storage\FileStorage;
use PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder;
use PhpCollective\Infrastructure\Storage\Utility\FilenameSanitizer;

// Storage setup
$storageFactory = new StorageAdapterFactory();
$storageService = new StorageService($storageFactory);

// Add Local storage adapter
$storageService->addAdapterConfig(
    'Local',
    LocalFactory::class,
    [
        'root' => WWW_ROOT . 'files' . DS,
    ],
);

// Configure path builder
$pathBuilder = new PathBuilder([
    'pathTemplate' => '{model}{ds}{collection}{ds}{randomPath}{ds}{strippedId}{ds}{filename}.{extension}',
    'variantPathTemplate' => '{model}{ds}{collection}{ds}{randomPath}{ds}{strippedId}{ds}{filename}.{hashedVariant}.{extension}',
    'randomPathLevels' => 1,
    'sanitizer' => new FilenameSanitizer([
        'urlSafe' => true,
        'removeUriReservedChars' => true,
        'maxLength' => 190,
    ]),
]);

// Create file storage instance
$fileStorage = new FileStorage($storageService, $pathBuilder);

// Store in configuration for behavior usage
Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => null, // Add image processor if needed
    'fileValidator' => null, // Add custom validator if needed
]);
```

### 3. Attach Behavior to Your Table

In your table class (e.g., `UsersTable.php`):

```php
public function initialize(array $config): void
{
    parent::initialize($config);

    // Add the FileStorage behavior
    $this->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
}
```

### Behavior Configuration Options

The behavior accepts these configuration options:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `fileStorage` | `FileStorage` | *required* | The FileStorage instance for storing files |
| `fileProcessor` | `ProcessorInterface\|null` | `null` | Image/file processor for generating variants |
| `fileValidator` | `string\|UploadValidatorInterface\|null` | `null` | Validator class or instance for upload validation |
| `fileField` | `string` | `'file'` | The field name in your form that contains the uploaded file |
| `defaultStorageConfig` | `string` | `'Local'` | Default storage adapter name |
| `ignoreEmptyFile` | `bool` | `true` | Skip processing when no file is uploaded |

#### The `fileField` Option

The `fileField` option defines which field name the behavior looks for in uploaded data. By default, it's `'file'`, meaning your form field should be named `*.file`:

```php
// Default: fileField => 'file'
echo $this->Form->control('avatar.file', ['type' => 'file']);
```

If you want to use a different field name, configure it:

```php
// In your table
$this->addBehavior('FileStorage.FileStorage', [
    'fileStorage' => $fileStorage,
    'fileField' => 'upload',  // Custom field name
]);

// In your form
echo $this->Form->control('avatar.upload', ['type' => 'file']);
```

This is useful when integrating with existing forms or APIs that use different field naming conventions.

## Adding File Upload to Your Model

### Create Association

In your table class (e.g., `PostsTable.php`):

```php
public function initialize(array $config): void
{
    parent::initialize($config);

    // Single file association (hasOne)
    $this->hasOne('CoverImages', [
        'className' => 'FileStorage.FileStorage',
        'foreignKey' => 'foreign_key',
        'conditions' => [
            'CoverImages.model' => 'Post',
            'CoverImages.collection' => 'Cover',
        ],
    ]);

    // Multiple files association (hasMany)
    $this->hasMany('GalleryImages', [
        'className' => 'FileStorage.FileStorage',
        'foreignKey' => 'foreign_key',
        'conditions' => [
            'GalleryImages.model' => 'Post',
            'GalleryImages.collection' => 'Gallery',
        ],
    ]);
}
```

### Make Entity Fields Accessible

In your entity (e.g., `Post.php`):

```php
protected array $_accessible = [
    'title' => true,
    'body' => true,
    'cover_image' => true,  // For hasOne association (singular property)
    'gallery_images' => true,  // For hasMany association (plural property)
    // ... other fields
];
```

## Uploading Files

### Form Template

```php
// For single file upload (hasOne)
<?= $this->Form->create($post, ['type' => 'file']) ?>
<?= $this->Form->control('title') ?>
<?= $this->Form->control('cover_image.file', ['type' => 'file', 'label' => 'Cover Image']) ?>
<?= $this->Form->button(__('Submit')) ?>
<?= $this->Form->end() ?>

// For multiple file uploads (hasMany)
<?= $this->Form->create($post, ['type' => 'file']) ?>
<?= $this->Form->control('title') ?>
<?= $this->Form->control('gallery_images.0.file', ['type' => 'file', 'label' => 'Gallery Image 1']) ?>
<?= $this->Form->control('gallery_images.1.file', ['type' => 'file', 'label' => 'Gallery Image 2']) ?>
<?= $this->Form->button(__('Submit')) ?>
<?= $this->Form->end() ?>
```

**Important**:
- The field must be named `*.file` - the behavior looks for this specific field name
- For hasOne associations, use singular property name (e.g., `cover_image.file`)
- For hasMany associations, use plural property name with index (e.g., `gallery_images.0.file`)

### Controller Action

```php
public function add()
{
    $post = $this->Posts->newEmptyEntity();

    if ($this->request->is('post')) {
        $post = $this->Posts->patchEntity($post, $this->request->getData());

        // Set required fields for file storage
        if (isset($post->cover_image)) {
            $post->cover_image->model = 'Post';
            $post->cover_image->collection = 'Cover';
            $post->cover_image->adapter = 'Local';
        }

        if ($this->Posts->save($post, ['associated' => ['CoverImages']])) {
            $this->Flash->success(__('The post has been saved.'));

            return $this->redirect(['action' => 'index']);
        }
        $this->Flash->error(__('The post could not be saved.'));
    }

    $this->set(compact('post'));
}
```

## Retrieving and Displaying Files

### Load Files with Entity

```php
// In controller
$post = $this->Posts->get($id, contain: ['CoverImages', 'GalleryImages']);
```

### Display in Template

```php
// Check if file exists (hasOne - singular property)
<?php if ($post->cover_image) { ?>
    <img src="/files/<?= h($post->cover_image->path) ?>" alt="Cover">
<?php } ?>

// Display multiple files (hasMany - plural property)
<?php foreach ($post->gallery_images as $image) { ?>
    <img src="/files/<?= h($image->path) ?>" alt="Gallery Image">
<?php } ?>
```

### Using Image Variants

If you have configured image variants (see below):

```php
<?php
// Get variant path (hasOne - singular property)
$variant = $post->cover_image->variants['thumbnail'] ?? null;
if ($variant) {
    ?>
    <img src="/files/<?= h($variant['path']) ?>" alt="Thumbnail">
    <?php
}
?>
```

## Image Variants

Image variants allow you to automatically generate different sizes/versions of uploaded images.

### Configure Image Processor

In your bootstrap/storage config:

```php
use Intervention\Image\ImageManager;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageProcessor;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageVariantCollection;

// Image Manager
$imageManager = new ImageManager(['driver' => 'gd']);

// Image Processor
$imageProcessor = new ImageProcessor($fileStorage, $pathBuilder, $imageManager);

// Define variants
$coverVariants = ImageVariantCollection::create();
$coverVariants->addNew('thumbnail')
    ->resize(150, 150)
    ->optimize();
$coverVariants->addNew('medium')
    ->resize(400, 400)
    ->optimize();
$coverVariants->addNew('large')
    ->fit(800, 600)
    ->optimize();

// Configure for your models
Configure::write('FileStorage.imageVariants', [
    'Posts' => [
        'Cover' => $coverVariants->toArray(),
    ],
    'Users' => [
        'Avatar' => $avatarVariants->toArray(),
    ],
]);

// Update behavior config to use processor
Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => $imageProcessor,
]);
```

### Variant Operations

Available operations on variants:

- `resize(width, height)` - Resize maintaining aspect ratio
- `fit(width, height)` - Resize and crop to exact dimensions
- `crop(width, height, x, y)` - Crop specific area
- `optimize()` - Optimize file size
- `greyscale()` - Convert to grayscale
- `blur(amount)` - Apply blur effect

See the [Image Storage and Versioning](Image-Storage-And-Versioning.md) documentation for more details.

## Deleting Files

Files are automatically deleted when you delete the entity, thanks to the behavior's `afterDelete` callback.

```php
// This will delete both the database record and the physical file
$this->Posts->delete($post);
```

### Delete File Storage Record Only

If you need to delete just the file storage record:

```php
$this->fetchTable('FileStorage.FileStorage')->delete($coverImage);
```

### Bulk Delete

**Important**: Never use `deleteAll()` as it doesn't trigger callbacks!

```php
// Wrong - files won't be deleted from storage
$this->fetchTable('FileStorage.FileStorage')->deleteAll(['model' => 'Post']);

// Right - use the behavior's helper method
$this->Posts->behaviors()->FileStorage->deleteAllFiles(['model' => 'Post']);
```

## Custom Storage Adapters

You can use different storage adapters beyond local filesystem.

### Amazon S3 Example

```php
use PhpCollective\Infrastructure\Storage\Factories\AwsS3Factory;

$storageService->addAdapterConfig(
    'S3',
    AwsS3Factory::class,
    [
        'key' => 'YOUR_AWS_KEY',
        'secret' => 'YOUR_AWS_SECRET',
        'region' => 'us-east-1',
        'bucket' => 'your-bucket-name',
    ],
);
```

Then when saving files, specify the adapter:

```php
$post->cover_image->adapter = 'S3';  // hasOne - singular property
```

## Validation

You can add custom validation for file uploads.

### Create Validator

```php
namespace App\FileStorage\Validator;

use Cake\Validation\Validator;
use FileStorage\Model\Validation\UploadValidatorInterface;

class ImageValidator implements UploadValidatorInterface
{
    public function configure(Validator $validator): Validator
    {
        $validator
            ->allowEmptyFile('file')
            ->add('file', 'validMimeType', [
                'rule' => ['mimeType', ['image/jpeg', 'image/png', 'image/gif']],
                'message' => 'Please upload only JPG, PNG, or GIF images.',
            ])
            ->add('file', 'validFileSize', [
                'rule' => ['fileSize', '<=', '5MB'],
                'message' => 'Image must be less than 5MB.',
            ]);

        return $validator;
    }
}
```

### Configure in Behavior

```php
Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => $imageProcessor,
    'fileValidator' => \App\FileStorage\Validator\ImageValidator::class,
]);
```

## Troubleshooting

### File Not Uploading

1. Check that your form has `'type' => 'file'`
2. Verify the field is named `*.file` (e.g., `cover_image.file` for hasOne, `gallery_images.0.file` for hasMany)
3. Ensure `model`, `collection`, and `adapter` fields are set on the entity
4. Check file permissions on the upload directory

### Images Not Processing

1. Verify GD or Imagick extension is installed
2. Check that `fileProcessor` is configured in behavior config
3. Ensure image variants are configured for your Model/Collection combination
4. Check error logs for processing errors

### Association Not Working

1. Verify `foreignKey` is set to `'foreign_key'`
2. Check that `conditions` include both `model` and `collection`
3. Ensure you're using `contain` when loading entities
4. Verify entity `$_accessible` includes the association field

## See Also

- [Quick Start Tutorial](../Tutorials/Quick-Start.md)
- [Image Storage and Versioning](Image-Storage-And-Versioning.md)
- [Validation](Validation.md)
- [Getting File Path and URL](Getting-a-File-Path-and-URL.md)
