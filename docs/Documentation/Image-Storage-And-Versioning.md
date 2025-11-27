Image Versioning
================

Image variants allow you to automatically generate different sizes/versions of uploaded images. The plugin uses the `php-collective/file-storage-image-processor` library for image processing.

## Requirements

Install the image processor library:

```bash
composer require php-collective/file-storage-image-processor
```

## Configuration

### Using ImageVariantCollection (Recommended)

The modern approach uses `ImageVariantCollection` to define variants with a fluent API:

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

### Understanding Model and Collection

The variant configuration uses a two-level hierarchy:

- **Model**: The table alias (e.g., `'Posts'`, `'Users'`) - comes from `$table->getAlias()`
- **Collection**: A grouping within a model (e.g., `'Avatar'`, `'Cover'`, `'Gallery'`)

This allows different variant configurations for different file types within the same model.

### Available Variant Operations

- `crop(width, height, x, y)` - Crop to exact dimensions at position (x, y optional)
- `resize(width, height)` - Resize to exact dimensions (does not preserve aspect ratio)
- `scale(width, height)` - Scale while preserving aspect ratio
- `cover(width, height)` - Smart crop to cover exact dimensions
- `widen(width)` - Resize by width, maintain aspect ratio
- `heighten(height)` - Resize by height, maintain aspect ratio
- `rotate(angle)` - Rotate by degrees
- `flip(direction)` - Flip 'h' (horizontal) or 'v' (vertical)
- `flipHorizontal()` - Flip horizontally
- `flipVertical()` - Flip vertically
- `sharpen(amount)` - Apply sharpening
- `optimize()` - Apply optimization if available on system
- `callback(callable)` - Custom processing with callback function

### Array Configuration (Alternative)

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

## Setting Up the Image Processor

Configure the image processor in your bootstrap or storage configuration:

```php
use Intervention\Image\ImageManager;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageProcessor;

// Create image manager (uses GD by default)
$imageManager = new ImageManager();

// Create image processor
$imageProcessor = new ImageProcessor(
    $fileStorage,
    $pathBuilder,
    $imageManager,
);

// Add to behavior configuration
Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => $imageProcessor,
]);
```

### Using Multiple Processors

You can stack multiple processors using `StackProcessor`:

```php
use PhpCollective\Infrastructure\Storage\Processor\StackProcessor;

$stackProcessor = new StackProcessor([
    $imageProcessor,
    $customProcessor, // Your custom processor
]);

Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => $stackProcessor,
]);
```

## File Storage Paths

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

## Accessing Variants

After upload, variant information is stored in the entity's `variants` field as JSON:

```php
// In your template
$entity = $this->Posts->get($id, contain: ['CoverImages']);

// Access original
$originalPath = $entity->cover_image->path;

// Access variant
$thumbPath = $entity->cover_image->variants['thumb']['path'] ?? null;
```

See also:
- [The Image Helper](The-Image-Helper.md) for displaying images
- [The Image Version Shell](The-Image-Version-Shell.md) for regenerating variants
- [Quick Start Tutorial](../Tutorials/Quick-Start.md) for a complete example
