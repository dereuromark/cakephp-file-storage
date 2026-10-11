# Paths and URLs

The path builder creates storage-relative paths when files are saved. The plugin
persists the resulting `path` and variant paths in the file row. Read those
values when serving existing files; rebuilding a path can give a different
result after configuration changes or deduplication.

## Read stored paths

```php
$file = $this->fetchTable('FileStorage.FileStorage')->get($id);
$originalPath = $file->path;
$thumbnailPath = $file->getVariantPath('thumb');
$thumbnailUrl = $file->getVariantUrl('thumb');
```

`getVariantPath()` and `getVariantUrl()` return `null` when the requested value
is absent. A URL exists only if your processor or URL builder stored one in
the variant metadata. The plugin entity has no `path()`, `url()` or
`pathBuilder()` methods, and there is no `FileStorage.Storage` helper.

## Public local files

If your adapter root is `WWW_ROOT . 'files' . DS`, the URL prefix is `files/`.
Load the Image helper in `AppView::initialize()`:

```php
$this->addHelper('FileStorage.Image', ['pathPrefix' => 'files/']);
```

```php
$url = $this->Image->imageUrl($file);
$thumbUrl = $this->Image->imageUrl($file, 'thumb');
```

`imageUrl()` throws when a path is missing. `display()` catches that error and
can render a fallback. Stored URLs take precedence over the prefix. See
[Image helper](/images/helper).

Files below `webroot` are publicly accessible. Neither the prefix nor a
controller route protects a file that the web server can serve directly.

## Private files

Store private files outside `webroot`. After authorizing access to the row,
generate a temporary URL to the original:

```php
$url = \FileStorage\Utility\SignedUrlGenerator::url($file, [
    'expires' => strtotime('+15 minutes'),
]);
```

The built-in action serves originals, not variants. Serve private variants from
an application controller that checks access and reads `getVariantPath()`.
See [Serving files](/serving/) and [Signed URLs](/serving/signed-urls).

## Building paths for new files

Configure `PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder` on
the storage service. Options include `pathTemplate`, `variantPathTemplate`,
`randomPathLevels`, and `filenameSanitizer`. Identity placeholders (`{id}`,
`{strippedId}`, `{randomPath}`) use the file's UUID, not its integer database id.

The plugin supplies a content hash to the builder only for deduplicated writes.
Those originals use `hashPathTemplate`, while variants retain per-row paths.
See [Deduplication](./deduplication).
