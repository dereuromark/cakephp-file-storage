# Configuration Reference

All configuration lives under the `FileStorage` key, typically in `config/app.php`
or a dedicated `config/storage.php`. The plugin ships a complete, commented
[`config/app.example.php`](https://github.com/dereuromark/cakephp-file-storage/blob/master/config/app.example.php)
you can copy from.

## Keys at a glance

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `pathPrefix` | `string` | `'img/'` | Prefix prepended to generated image paths/URLs. |
| `signatureSecret` | `string` | `Security.salt` | HMAC secret for [signed URLs](/serving/signed-urls). |
| `adminAccess` | `bool\|Closure\|null` | `null` | [Admin backend](#admin) access gate (fail-closed). |
| `standalone` | `bool` | `false` | Run the admin backend independent of your `AppController`. |
| `adminLayout` | `string\|false\|null` | `null` | Layout used by the admin backend. |
| `adminBackUrl` | `array\|string` | *(unset)* | Optional "back to app" link in the admin header. |
| `adminBackLabel` | `string` | `'Back to App'` | Label for `adminBackUrl`. |
| `imageVariants` | `array` | `[]` | [Variant definitions](/images/) keyed by `[Model][Collection]`. |
| `useEntityModelForVariants` | `bool` | `false` | Use `file_storage.model` instead of the FileStorage table alias for inline variant lookup. |
| `behaviorConfig` | `array` | `[]` | Default config for the [FileStorage behavior](./behavior). |
| `serveRoute` | `array` | *(unset)* | Route to your custom [serving controller](/serving/). |

## Image and path settings

### `pathPrefix`

Prefix prepended to generated image paths and URLs (used by the
[Image helper](/images/helper)). Defaults to `'img/'`.

### `imageVariants`

Variant definitions in a two-level hierarchy — `[ModelAlias][CollectionName]`.
See [Image variants and versioning](/images/) for the full operation list.

```php
'imageVariants' => [
    'Users' => [
        'Avatar' => $collection->toArray(),
    ],
],
```

### `useEntityModelForVariants`

By default, inline upload processing resolves the first `imageVariants` key from
the current FileStorage table alias. This keeps existing alias-keyed
configurations working in the current major release.

Set `useEntityModelForVariants` to `true` to resolve variants from the persisted
`file_storage.model` field instead. This is recommended for new apps and for apps
that use app-table associations such as `Users -> Avatars`.

```php
'FileStorage' => [
    'useEntityModelForVariants' => true,
    'imageVariants' => [
        'Users' => [
            'Avatar' => $collection->toArray(),
        ],
    ],
],
```

When this flag is enabled, audit existing variant config that was keyed by the
association alias:

```php
// Legacy alias-keyed config
'imageVariants' => [
    'Avatars' => [
        'Avatar' => $collection->toArray(),
    ],
],

// Entity-model-keyed config
'imageVariants' => [
    'Users' => [
        'Avatar' => $collection->toArray(),
    ],
],
```

### `behaviorConfig`

The default options array passed to the FileStorage behavior. See the
[Behavior Options reference](./behavior) for every key.

```php
'behaviorConfig' => [
    'fileStorage' => $fileStorage,   // required FileStorage instance
    'fileProcessor' => null,         // image/file processor
    'fileValidator' => null,         // upload validator class/instance
    // 'dataTransformer' => null,    // entity<->file transformer for the queue task
],
```

## Signed URLs

### `signatureSecret`

The secret used to sign temporary file-access URLs
([`SignedUrlGenerator`](/serving/signed-urls), HMAC-SHA256). It should be a
strong, random, app-specific string kept secret — anyone with it can forge valid
signed URLs. No default is baked in: when unset, it falls back to the app's
`Security.salt`. Set it explicitly to decouple signed-URL invalidation from the
salt.

```php
'signatureSecret' => env('FILE_STORAGE_SECRET'),
```

## Admin

### `adminAccess`

The admin backend is fail-closed: leaving this unset (or `null`) means every
action returns 403. Opt in with one of:

- `true` — trust an upstream gate (Authentication + Authorization, TinyAuth,
  custom middleware) on the `Admin` prefix.
- `Closure(\Cake\Http\ServerRequest $request): bool` — return `true` to allow the
  request.

See the [Admin Backend](/admin/) page for examples.

### `standalone`

When `true`, the admin controllers run independent of the host application's
`App\Controller\AppController` (skipping its `initialize()` chain, loading only
Flash). Useful for projects without their own admin shell. Leave `false`
(default) to inherit your `AppController`'s components.

### `adminLayout`

The bundled Bootstrap 5 / Font Awesome 6 admin layout (CDN with SRI):

- `null` — use the bundled `FileStorage.file_storage` layout (default).
- `false` — fall back to the host application's default layout.
- `string` — use the given layout, e.g. `'App.admin'`.

### `adminBackUrl` / `adminBackLabel`

An opt-in "back to app" link in the admin header. When set, an outline button
appears in the top navbar so admins can escape the plugin-isolated layout.
`adminBackUrl` accepts anything `Router::url()` takes — a Cake URL array, a path
string, or a full URL. Use `'plugin' => false` to anchor the builder to the host
app rather than the FileStorage plugin.

```php
'adminBackUrl' => ['plugin' => false, 'prefix' => 'Admin', 'controller' => 'Overview', 'action' => 'index'],
'adminBackLabel' => 'Back to admin', // optional, defaults to "Back to App"
```

## Serving

### `serveRoute`

The route to your custom [serving controller](/serving/), used for URL
generation:

```php
'serveRoute' => [
    'controller' => 'Images',
    'action' => 'display',
    'plugin' => false,
],
```
