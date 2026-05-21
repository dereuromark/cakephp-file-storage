# The Image Helper

The plugin ships an `Image` helper that makes it easy to display images and their
variants stored through the FileStorage plugin.

## Loading the helper

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
    'pathPrefix' => 'img/', // prefix added to all image paths (default: 'img/')
]);
```

The helper also reads configuration from `Configure::read('FileStorage')`, so you
can set defaults globally.

## Displaying images

### Display the original image

```php
echo $this->Image->display($entity->cover_image);
```

This renders an `<img>` tag with the image path.

### Display a variant

```php
echo $this->Image->display($entity->cover_image, 'thumb');
echo $this->Image->display($entity->cover_image, 'medium');
echo $this->Image->display($entity->cover_image, 'large');
```

### With HTML attributes

Pass HTML attributes as the third argument:

```php
echo $this->Image->display($entity->cover_image, 'thumb', [
    'class' => 'img-thumbnail',
    'alt' => 'Product image',
    'title' => $entity->name,
]);
```

## Getting image URLs

If you need just the URL (not the full `<img>` tag):

```php
// URL to the original image
$url = $this->Image->imageUrl($entity->cover_image);

// URL to a variant
$thumbUrl = $this->Image->imageUrl($entity->cover_image, 'thumb');

// Use in custom markup
echo $this->Html->link(
    $this->Html->image($thumbUrl),
    $this->Image->imageUrl($entity->cover_image), // link to full size
    ['escape' => false],
);
```

## Fallback images

When an image entity is null or a variant doesn't exist, you can display a
fallback image.

### Using the default placeholder path

Set `fallback` to `true` to use `placeholder/{variant}.jpg`:

```php
echo $this->Image->display($entity->cover_image, 'thumb', [
    'fallback' => true,
]);
// If the image is null, displays: webroot/img/placeholder/thumb.jpg
```

### Using a custom fallback image

```php
echo $this->Image->display($entity->cover_image, 'thumb', [
    'fallback' => 'default-product.png',
]);
// If the image is null, displays: webroot/img/default-product.png
```

### Handling missing variants

If you request a variant that doesn't exist, the helper will:

1. Log a debug message.
2. Return the fallback image (if configured).
3. Return an empty string (if no fallback).

## Example: product gallery

```php
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

## Example: user avatar with fallback

```php
<?= $this->Image->display($user->avatar, 'small', [
    'class' => 'avatar rounded-circle',
    'alt' => $user->name,
    'fallback' => 'default-avatar.png',
]) ?>
```

## Modern image formats via `picture()`

AVIF and WebP are 25–60% smaller than JPEG/PNG at comparable quality but need
`<picture>` + `<source type>` content negotiation to ship safely alongside the
original encoding. `Image->picture()` builds that markup:

```php
echo $this->Image->picture($article->cover, 'medium');
```

Renders (when alt-format variants exist):

```html
<picture>
  <source srcset="/img/.../cover.medium.avif" type="image/avif">
  <source srcset="/img/.../cover.medium.webp" type="image/webp">
  <img src="/img/.../cover.medium.jpg" alt="">
</picture>
```

Browsers pick the first `<source>` whose `type` they understand; everything else
falls through to the inner `<img>`.

### Producing the alt-format variants

`picture()` doesn't encode anything itself — it just looks up variants named
`{version}.{format}`. Declare them in your `FileStorage.imageVariants` config
alongside the base variant:

```php
'FileStorage' => [
    'imageVariants' => [
        'Articles' => [
            'cover' => [
                'medium'      => ['width' => 800, 'format' => 'jpeg'],
                'medium.webp' => ['width' => 800, 'format' => 'webp'],
                'medium.avif' => ['width' => 800, 'format' => 'avif'],
            ],
        ],
    ],
],
```

Run `bin/cake file_storage generate_image_variant Articles cover` once to
backfill existing rows. New uploads pick up all three formats automatically via
the standard processor pipeline.

### Behavior details

- **Graceful degradation** — a format whose variant isn't defined (or doesn't
  exist on the storage adapter) is silently skipped. Browsers fall through to the
  next `<source>` or, ultimately, the `<img>`.
- **No alt-format variants at all** → `picture()` returns just the plain `<img>`
  without the `<picture>` wrapper, avoiding the visual cost of wrapping the only
  child in a redundant element.
- **Preference order matters** — the default is `['avif', 'webp']` (AVIF first
  because it's the most efficient where supported, WebP as a near-universal
  fallback). Override per call with `formats`:

  ```php
  echo $this->Image->picture($article->cover, 'medium', [
      'formats' => ['webp'], // skip AVIF if you don't generate it
  ]);
  ```

- **All other options** (`fallback`, `pathPrefix`, alt text, `class`) forward to
  the inner `<img>` via the existing `display()` path.

## Working with entities

The helper expects entities that implement `FileStorageEntityInterface`. The
plugin's `FileStorage` entity implements this interface and provides:

- `path` — path to the original file.
- `url` — optional URL field (if set, used instead of `path`).
- `getVariantPath($variant)` — path to a specific variant.
- `getVariantUrl($variant)` — URL for a specific variant (if stored).
- `variants` — array of variant information.

## See also

- [Image variants and versioning](./) — configuring image variants.
- [Usage](/guide/usage) — the complete usage guide.
- [Quick Start](/guide/quick-start) — a step-by-step example.
