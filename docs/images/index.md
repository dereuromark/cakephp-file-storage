# Image Variants and Versioning

Image variants let you automatically generate different sizes/versions of
uploaded images. The plugin uses the `php-collective/file-storage-image-processor`
library for image processing.

## Requirements

Install the image processor library:

```bash
composer require php-collective/file-storage-image-processor
```

Processor 1.x supports PHP 8.2 and Intervention Image 3. Processor 2.x requires
PHP 8.3+ and Intervention Image 4. Enable GD or Imagick; format support depends
on your driver build. The examples below use methods available in both versions.

## Configuration

### Using ImageVariantCollection (recommended)

The modern approach uses `ImageVariantCollection` to define variants with a
fluent API:

```php
use Cake\Core\Configure;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageVariantCollection;

// Create a variant collection
$collection = ImageVariantCollection::create();

// Add variants with various operations
$collection->addNew('thumb')
    ->cover(100, 100);

$collection->addNew('medium')
    ->resize(400, 400)
    ->optimize();

$collection->addNew('large')
    ->cover(800, 600)
    ->optimize();

// Configure in your application
Configure::write('FileStorage.imageVariants', [
    // Structure: [ModelAlias][CollectionName] => variants
    'Posts' => [
        'Cover' => $collection->toArray(),
    ],
]);
```

### Understanding model and collection

The variant configuration uses a two-level hierarchy:

- **Model**: the stored `file_storage.model` value (e.g. `Posts`, `Users`).
- **Collection**: a grouping within a model (e.g. `Avatar`, `Cover`, `Gallery`).

This allows different variant configurations for different file types within the
same model.

### Available variant operations

- `crop(width, height, x, y)`: crop to exact dimensions at position (x, y optional).
- `resize(width, height)`: resize to exact dimensions (does not preserve aspect ratio).
- `scale(width, height)`: scale while preserving aspect ratio.
- `cover(width, height)`: smart crop to cover exact dimensions.
- `widen(width)`: resize by width, maintain aspect ratio.
- `heighten(height)`: resize by height, maintain aspect ratio.
- `rotate(angle)`: rotate by degrees.
- `flip(direction)`: flip `'h'` (horizontal) or `'v'` (vertical).
- `flipHorizontal()`: flip horizontally.
- `flipVertical()`: flip vertically.
- `sharpen(amount)`: apply sharpening.
- `optimize()`: apply optimization if available on the system.
- `callback(callable)`: custom processing with a callback function.

### Array configuration (alternative)

You can also configure variants using arrays directly:

```php
Configure::write('FileStorage.imageVariants', [
    'Users' => [
        'Avatars' => [
            'thumb' => [
                'operations' => ['cover' => ['width' => 50, 'height' => 50]],
            ],
            'medium' => [
                'operations' => ['cover' => ['width' => 150, 'height' => 150]],
            ],
        ],
    ],
]);
```

Each variant array needs an `operations` map. Flat `width`, `height`, `mode` or
`format` keys do not define processing operations. Use the fluent builder to
avoid constructing these arrays by hand.

## Setting up the image processor

Configure the image processor in your bootstrap or storage configuration:

```php
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageProcessor;

// Create the image manager with the GD driver
$imageManager = new ImageManager(new Driver());

// Create the image processor
$imageProcessor = new ImageProcessor(
    $fileStorage,
    $pathBuilder,
    $imageManager,
);

// Add to the behavior configuration
Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => $imageProcessor,
]);
```

### Using multiple processors

You can stack multiple processors using `StackProcessor`:

```php
use PhpCollective\Infrastructure\Storage\Processor\StackProcessor;

$stackProcessor = new StackProcessor([
    $imageProcessor,
    $customProcessor, // your custom processor
]);

Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => $stackProcessor,
]);
```

## File storage paths

Image files are stored using the configured path builder. With the default builder, ordinary originals use:

```
{model}/{randomPath}/{strippedId}/{filename}.{extension}
```

For example:

```
Users/a1/b2/c3/<uuid-without-hyphens>/profile.jpg
```

Variant files are stored alongside the original with a hashed variant name:

```
Users/a1/b2/c3/<uuid-without-hyphens>/profile.abc123.jpg
```

## Accessing variants

After upload, variant information is stored in the entity's `variants` field as
JSON:

```php
$entity = $this->Posts->get($id, contain: ['CoverImages']);

// Access the original
$originalPath = $entity->cover_image->path;

// Access a variant
$thumbPath = $entity->cover_image->variants['thumb']['path'] ?? null;
```

## See also

- [The Image helper](./helper): display images and variants in templates.
- [The variant command](./command): generate or regenerate variants from the CLI.
- [Quick Start](/guide/quick-start): a complete avatar example.
