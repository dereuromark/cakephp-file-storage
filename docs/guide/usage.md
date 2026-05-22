# Usage

This guide covers the essential concepts and usage patterns for the FileStorage
plugin.

## Basic concepts

### The file storage model

The plugin uses a single table (`file_storage`) to track all uploaded files in
your application. Each record contains:

| Field | Description |
|-------|-------------|
| `id` | Unique UUID for the file. |
| `foreign_key` | The id of the entity this file belongs to (e.g. user id, post id). Type follows the global `Polymorphic.type` config (default `integer`) — see [Foreign key column types](./installation#foreign-key-column-types). |
| `model` | The model name (e.g. `Users`, `Posts`). |
| `collection` | The collection / type of file (e.g. `Avatar`, `Cover`, `Gallery`). |
| `filename` | Original filename. |
| `filesize` | Size in bytes. |
| `mime_type` | File MIME type. |
| `extension` | File extension. |
| `path` | Storage path. |
| `adapter` | Storage adapter name (e.g. `Local`, `S3`). |
| `variants` | JSON array of image variant information (for images). |
| `metadata` | JSON array for additional metadata. |

### Model vs collection

This distinction is important:

- **Model** — the table alias (e.g. `Users`, `Posts`), from `$this->table()->getAlias()`.
- **Collection** — a grouping within a model (e.g. `Avatar`, `Cover`, `Gallery`).

For example, a `Posts` model might have a `Cover` collection for cover images, a
`Gallery` collection for gallery images, and an `Attachments` collection for PDF
attachments.

## Setting up file storage

### 1. Install and load the plugin

See the [Installation](./installation) guide.

### 2. Configure the storage service

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

// Add a Local storage adapter
$storageService->addAdapterConfig(
    'Local',
    LocalFactory::class,
    [
        'root' => WWW_ROOT . 'files' . DS,
    ],
);

// Configure the path builder
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

// Create the file storage instance
$fileStorage = new FileStorage($storageService, $pathBuilder);

// Store in configuration for behavior usage
Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => null, // add an image processor if needed
    'fileValidator' => null, // add a custom validator if needed
]);
```

### 3. Attach the behavior to your table

In your table class (e.g. `UsersTable.php`):

```php
public function initialize(array $config): void
{
    parent::initialize($config);

    $this->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
}
```

The behavior accepts several options — `fileStorage` (required), `fileProcessor`,
`fileValidator`, `fileField`, `defaultStorageConfig`, and `ignoreEmptyFile`. See
the [Behavior Options reference](/reference/behavior) for the full list.

#### The `fileField` option

By default the behavior looks for a `'file'` field in the uploaded data, so your
form field should be named `*.file`:

```php
// Default: fileField => 'file'
echo $this->Form->control('avatar.file', ['type' => 'file']);
```

To use a different field name, configure it:

```php
$this->addBehavior('FileStorage.FileStorage', [
    'fileStorage' => $fileStorage,
    'fileField' => 'upload', // custom field name
]);
```

```php
echo $this->Form->control('avatar.upload', ['type' => 'file']);
```

This is useful when integrating with existing forms or APIs that use different
field naming conventions.

## Adding file upload to your model

### Create the association

In your table class (e.g. `PostsTable.php`):

```php
public function initialize(array $config): void
{
    parent::initialize($config);

    // Single file association (hasOne)
    $this->hasOne('CoverImages', [
        'className' => 'FileStorage.FileStorage',
        'foreignKey' => 'foreign_key',
        'conditions' => [
            'CoverImages.model' => 'Posts',
            'CoverImages.collection' => 'Cover',
        ],
    ]);

    // Multiple files association (hasMany)
    $this->hasMany('GalleryImages', [
        'className' => 'FileStorage.FileStorage',
        'foreignKey' => 'foreign_key',
        'conditions' => [
            'GalleryImages.model' => 'Posts',
            'GalleryImages.collection' => 'Gallery',
        ],
    ]);
}
```

### Make the entity fields accessible

In your entity (e.g. `Post.php`):

```php
protected array $_accessible = [
    'title' => true,
    'body' => true,
    'cover_image' => true,   // hasOne association (singular property)
    'gallery_images' => true, // hasMany association (plural property)
    // … other fields
];
```

## Uploading files

### Form template

```php
// Single file upload (hasOne)
<?= $this->Form->create($post, ['type' => 'file']) ?>
<?= $this->Form->control('title') ?>
<?= $this->Form->control('cover_image.file', ['type' => 'file', 'label' => 'Cover Image']) ?>
<?= $this->Form->button(__('Submit')) ?>
<?= $this->Form->end() ?>

// Multiple file uploads (hasMany)
<?= $this->Form->create($post, ['type' => 'file']) ?>
<?= $this->Form->control('title') ?>
<?= $this->Form->control('gallery_images.0.file', ['type' => 'file', 'label' => 'Gallery Image 1']) ?>
<?= $this->Form->control('gallery_images.1.file', ['type' => 'file', 'label' => 'Gallery Image 2']) ?>
<?= $this->Form->button(__('Submit')) ?>
<?= $this->Form->end() ?>
```

::: warning Field naming
- The field must be named `*.file` — the behavior looks for this specific name.
- For `hasOne`, use the singular property name (e.g. `cover_image.file`).
- For `hasMany`, use the plural property name with an index (e.g. `gallery_images.0.file`).
:::

### Controller action

```php
public function add()
{
    $post = $this->Posts->newEmptyEntity();

    if ($this->request->is('post')) {
        $post = $this->Posts->patchEntity($post, $this->request->getData());

        // Set the required fields for file storage
        if (isset($post->cover_image)) {
            $post->cover_image->model = 'Posts';
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

## Retrieving and displaying files

### Load files with the entity

```php
$post = $this->Posts->get($id, contain: ['CoverImages', 'GalleryImages']);
```

### Display in a template

```php
// hasOne — singular property
<?php if ($post->cover_image) { ?>
    <img src="/files/<?= h($post->cover_image->path) ?>" alt="Cover">
<?php } ?>

// hasMany — plural property
<?php foreach ($post->gallery_images as $image) { ?>
    <img src="/files/<?= h($image->path) ?>" alt="Gallery Image">
<?php } ?>
```

For displaying image variants, prefer the [Image helper](/images/helper) — it
handles variant lookup, fallbacks, and modern formats for you.

## Deleting files

Files are automatically deleted when you delete the entity, thanks to the
behavior's `afterDelete` callback:

```php
// Deletes both the database record and the physical file
$this->Posts->delete($post);
```

### Delete a single file storage record

```php
$this->fetchTable('FileStorage.FileStorage')->delete($coverImage);
```

### Bulk delete

::: danger Never use deleteAll()
`deleteAll()` does not trigger callbacks, so the physical files are left behind
on disk. Use the behavior helper instead.
:::

```php
// Wrong — files won't be deleted from storage
$this->fetchTable('FileStorage.FileStorage')->deleteAll(['model' => 'Posts']);

// Right — use the behavior's helper method
$this->Posts->behaviors()->FileStorage->deleteAllFiles(['model' => 'Posts']);
```

## Custom storage adapters

You can use storage backends beyond the local filesystem.

### Amazon S3 example

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

Then specify the adapter when saving files:

```php
$post->cover_image->adapter = 'S3'; // hasOne — singular property
```

## See also

- [Validation](./validation) — validate uploads server-side.
- [Image variants and versioning](/images/) — automatic thumbnails and crops.
- [Paths and URLs](./paths-and-urls) — build file paths and URLs anywhere.
- [Troubleshooting](/reference/troubleshooting) — common pitfalls and fixes.
