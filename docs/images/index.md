# Image Variants and Versioning

Image variants let you automatically generate different sizes/versions of
uploaded images. The plugin uses the `php-collective/file-storage-image-processor`
library for image processing.

## Requirements

Install the image processor library:

```bash
composer require php-collective/file-storage-image-processor
```

## Configuration

### Using ImageVariantCollection (recommended)

The modern approach uses `ImageVariantCollection` to define variants with a
fluent API:

```php
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageVariantCollection;

// Create a variant collection
$collection = ImageVariantCollection::create();

// Add variants with various operations
$collection->addNew('thumb')
    ->crop(100, 100);

$collection->addNew('medium')
    ->resize(400, 400)
    ->optimize();

$collection->addNew('large')
    ->fit(800, 600)
    ->optimize();

// Configure in your application
Configure::write('FileStorage', [
    'imageVariants' => [
        // Structure: [ModelAlias][CollectionName] => variants
        'Posts' => [
            'Cover' => $collection->toArray(),
            'Gallery' => $galleryVariants->toArray(),
        ],
        'Users' => [
            'Avatar' => $avatarVariants->toArray(),
        ],
    ],
]);
```

### Understanding model and collection

The variant configuration uses a two-level hierarchy:

- **Model** — the table alias (e.g. `Posts`, `Users`), from `$table->getAlias()`.
- **Collection** — a grouping within a model (e.g. `Avatar`, `Cover`, `Gallery`).

This allows different variant configurations for different file types within the
same model.

### Available variant operations

- `crop(width, height, x, y)` — crop to exact dimensions at position (x, y optional).
- `resize(width, height)` — resize to exact dimensions (does not preserve aspect ratio).
- `scale(width, height)` — scale while preserving aspect ratio.
- `cover(width, height)` — smart crop to cover exact dimensions.
- `widen(width)` — resize by width, maintain aspect ratio.
- `heighten(height)` — resize by height, maintain aspect ratio.
- `rotate(angle)` — rotate by degrees.
- `flip(direction)` — flip `'h'` (horizontal) or `'v'` (vertical).
- `flipHorizontal()` — flip horizontally.
- `flipVertical()` — flip vertically.
- `sharpen(amount)` — apply sharpening.
- `optimize()` — apply optimization if available on the system.
- `callback(callable)` — custom processing with a callback function.

### Array configuration (alternative)

You can also configure variants using arrays directly:

```php
Configure::write('FileStorage', [
    'imageVariants' => [
        'Users' => [
            'Avatar' => [
                'thumb' => [
                    'width' => 50,
                    'height' => 50,
                    'mode' => 'crop',
                ],
                'medium' => [
                    'width' => 150,
                    'height' => 150,
                    'mode' => 'crop',
                ],
            ],
        ],
    ],
]);
```

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

Image files are stored using the configured path builder. The default pattern is:

```
/{model}/{collection}/{randomPath}/{strippedId}/{filename}.{extension}
```

For example:

```
/Users/Avatar/a1/b2c3d4e5/profile.jpg
```

Variant files are stored alongside the original with a hashed variant name:

```
/Users/Avatar/a1/b2c3d4e5/profile.abc123.jpg
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

- [The Image helper](./helper) — display images and variants in templates.
- [The variant command](./command) — generate or regenerate variants from the CLI.
- [Quick Start](/guide/quick-start) — a complete avatar example.
