The Image Helper
================

The plugin comes with an Image helper that makes it easy to display images and their variants stored through the FileStorage plugin.

## Loading the Helper

Load the helper in your `AppView`:

```php
namespace App\View;

use Cake\View\View;

class AppView extends View
{
    public function initialize(): void
    {
        parent::initialize();
        $this->addHelper('FileStorage.Image');
    }
}
```

## Configuration

The helper accepts the following configuration options:

```php
$this->addHelper('FileStorage.Image', [
    'pathPrefix' => 'img/',  // Prefix added to all image paths (default: 'img/')
]);
```

The helper also reads configuration from `Configure::read('FileStorage')`, so you can set defaults globally.

## Displaying Images

### Display Original Image

To display the original uploaded image:

```php
echo $this->Image->display($entity->cover_image);
```

This renders an `<img>` tag with the image path.

### Display Image Variant

To display a specific variant (thumbnail, medium, etc.):

```php
echo $this->Image->display($entity->cover_image, 'thumb');
echo $this->Image->display($entity->cover_image, 'medium');
echo $this->Image->display($entity->cover_image, 'large');
```

### With HTML Attributes

Pass HTML attributes as the third argument:

```php
echo $this->Image->display($entity->cover_image, 'thumb', [
    'class' => 'img-thumbnail',
    'alt' => 'Product image',
    'title' => $entity->name,
]);
```

## Getting Image URLs

If you need just the URL (not the full `<img>` tag):

```php
// Get URL to original image
$url = $this->Image->imageUrl($entity->cover_image);

// Get URL to a variant
$thumbUrl = $this->Image->imageUrl($entity->cover_image, 'thumb');

// Use in custom markup
echo $this->Html->link(
    $this->Html->image($thumbUrl),
    $this->Image->imageUrl($entity->cover_image), // Link to full size
    ['escape' => false]
);
```

## Fallback Images

When an image entity is null or a variant doesn't exist, you can display a fallback image.

### Using Default Placeholder Path

Set `fallback` to `true` to use `placeholder/{variant}.jpg`:

```php
echo $this->Image->display($entity->cover_image, 'thumb', [
    'fallback' => true,
]);
// If image is null, displays: webroot/img/placeholder/thumb.jpg
```

### Using Custom Fallback Image

Provide a custom fallback image path:

```php
echo $this->Image->display($entity->cover_image, 'thumb', [
    'fallback' => 'default-product.png',
]);
// If image is null, displays: webroot/img/default-product.png
```

### Handling Missing Variants

If you request a variant that doesn't exist, the helper will:
1. Log a debug message
2. Return the fallback image (if configured)
3. Return an empty string (if no fallback)

## Example: Product Gallery

```php
// In your template
<?php foreach ($product->gallery_images as $image) { ?>
    <div class="gallery-item">
        <a href="<?= $this->Image->imageUrl($image) ?>" data-lightbox="gallery">
            <?= $this->Image->display($image, 'thumb', [
                'class' => 'img-thumbnail',
                'alt' => $product->name,
                'fallback' => 'no-image.png',
            ]) ?>
        </a>
    </div>
<?php } ?>
```

## Example: User Avatar with Fallback

```php
<?= $this->Image->display($user->avatar, 'small', [
    'class' => 'avatar rounded-circle',
    'alt' => $user->name,
    'fallback' => 'default-avatar.png',
]) ?>
```

## Working with Entities

The helper expects entities that implement `FileStorageEntityInterface`. The plugin's `FileStorage` entity implements this interface and provides:

- `path` - Path to the original file
- `url` - Optional URL field (if set, used instead of path)
- `getVariantPath($variant)` - Get path to a specific variant
- `getVariantUrl($variant)` - Get URL for a specific variant (if stored)
- `variants` - Array of variant information

## See Also

- [Image Storage and Versioning](Image-Storage-And-Versioning.md) - Configuring image variants
- [How to Use](How-To-Use.md) - Complete usage guide
- [Quick Start Tutorial](../Tutorials/Quick-Start.md) - Step-by-step example
