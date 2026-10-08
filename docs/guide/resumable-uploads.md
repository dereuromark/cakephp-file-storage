# Resumable uploads

> [!TIP] Added in 5.3
> Requires the `CreateFileStorageUploads` migration, see [upgrading](./upgrading).

Upload large files in chunks, then attach the finished upload after saving the application's entity. The endpoint supports tus 1.0.0 core with creation, expiration, and termination. It is disabled until you add routes and an authorizer.

## Enable the endpoint

Run the plugin migrations, then add these routes in your application:

```php
use Cake\Routing\RouteBuilder;

$routes->plugin('FileStorage', function (RouteBuilder $routes): void {
    $routes->connect('/uploads', ['controller' => 'Uploads', 'action' => 'collection']);
    $routes->connect('/uploads/{id}', ['controller' => 'Uploads', 'action' => 'resource'], ['pass' => ['id']]);
});
```

Configure `FileStorage.resumable.authorizer`. It receives an action, upload details, and context. Return `false` to deny, or `['userId' => '42']` to allow. The ID must be a nonempty string of at most 36 bytes and must match the session owner on existing uploads.

```php
'authorizer' => static function (string $action, array $upload, array $context): array|false {
    if ($action === 'consume') {
        $userId = $context['userId'] ?? null;
        // Check access to the model, collection, and $upload['data']['foreign_key'] here.
        return $userId !== null ? ['userId' => (string)$userId] : false;
    }
    $identity = $context['request']->getAttribute('identity');

    return $identity ? ['userId' => (string)$identity->id] : false;
},
```

Actions are `create`, `read`, `write`, `delete`, and `consume`. Upload details include size, model, collection, and filename. Existing sessions also include `id` and `owner`; consume includes the attachment fields under `data`. HTTP context contains the PSR-7 request. Consume receives the context you pass to it.

The controller inherits your application's `AppController`. Keep CSRF protection enabled. Configure FormProtection for these bodyless and binary actions in the application as appropriate; tus requests do not carry CakePHP form field tokens. Cross-origin access and CORS headers are also application responsibilities.

## Send chunks

Install `tus-js-client` in your application. Pass the CSRF token through a header on every request:

```js
import { Upload } from 'tus-js-client'

const upload = new Upload(file, {
  endpoint: '/file-storage/uploads',
  chunkSize: 5 * 1024 * 1024,
  retryDelays: [0, 1000, 3000, 5000],
  headers: { 'X-CSRF-Token': csrfToken },
  metadata: {
    model: 'Documents',
    collection: 'Attachments',
    filename: file.name,
    filetype: file.type,
    // Optional: a SHA-256 digest computed from the whole file, lowercase hex.
    ...(sha256 ? { sha256 } : {}),
  },
  onSuccess() {
    const uploadId = upload.url.split('/').pop()
    // Submit uploadId with your application's form.
  },
})

const previous = await upload.findPreviousUploads()
if (previous.length) upload.resumeFromPreviousUpload(previous[0])
upload.start()
```

Use `upload.abort()` to pause and `upload.start()` to resume. Metadata requires `model`; the other recognized keys are optional. Model segments start with a letter and contain letters, digits, or underscores, separated by dots. Collection names contain letters, digits, underscores, or hyphens and start with a letter or digit. Filename must be a basename of at most 255 bytes. MIME type is a hint. Raw metadata is limited to 4096 bytes.

## Attach the completed file

Save your entity first, outside the consume transaction:

```php
use FileStorage\Service\ResumableUploads;

$document = $this->Documents->saveOrFail($document);
$file = (new ResumableUploads())->consume(
    $uploadId,
    ['foreign_key' => $document->id, 'user_id' => $identity->id],
    ['userId' => (string)$identity->id],
);
```

Model, collection, filename, and computed digest come from the session. You cannot override file or storage fields in consume data. The normal validation, deduplication, variant generation, and save events run. A validation or save failure raises `UploadInvalidException` with the entity in `$exception->entity`; the session stays complete for retry. Other upload failures extend `UploadException`.

`consume()` refuses an outer transaction and holds the part-file lock through its own commit. A failed consume may leave a stored file because storage writes cannot roll back. Local orphan cleanup and deduplicated blob cleanup cover their usual storage areas; ordinary files on remote adapters can remain.

`FileStorage.uploadCompleted` receives the session array as `upload` after the completing PATCH or a zero-length POST. It fires at most once and is not retried. Use form submission or another reconciliation path when your application needs guaranteed consumption.

## Limits and cleanup

All settings live under `FileStorage.resumable`:

| Key | Default | Purpose |
| --- | --- | --- |
| `authorizer` | `null` | Deny by default |
| `path` | `TMP . 'file_storage_uploads'` | Private local staging directory |
| `maxSize` | 5 GiB | Maximum declared file size; zero disables this cap |
| `maxBytesPerOwner` | 10 GiB | Declared bytes in unconsumed sessions |
| `maxSessions` | 10 | Unconsumed sessions per owner |
| `maxReservedBytes` | 50 GiB | Declared bytes with a remaining part file |
| `minFreeBytes` | 1 GiB | Free-space floor after outstanding reservations |
| `expires` | 86400 seconds | Uploading lifetime renewed by each chunk |
| `completedExpires` | 86400 seconds | Lifetime after completion and again after consume |

Reservations include expired sessions until their parts are removed. Schedule `bin/cake file_storage cleanup --uploadsOnly`, for example hourly. `--dryRun` previews expired rows and abandoned parts. The full cleanup command also runs this pass. Busy uploads are skipped.

## Deployment and limits

Use one application server with a private writable directory. Part files and admission locks live on its disk. Multiple servers require a shared directory with reliable `flock`; without it, bytes and quotas can become inconsistent. Local `fsync` durability depends on the filesystem, particularly on network mounts.

Web server and proxy body limits cap each chunk, not the file. Set `chunkSize` below them. On FrankenPHP, `post_max_size` did not apply to `PATCH` bodies: a 150 MiB chunk went through with `post_max_size = 100M`. Other SAPIs and proxies were not measured, so check yours before choosing a large chunk size. Rate limits and slow senders belong to the web server.

When a connection drops in the middle of a chunk, the server keeps the bytes that arrived and the client continues from the offset that `HEAD` reports. Behind a proxy that buffers request bodies, a partial chunk never reaches PHP and the offset stays at the last complete chunk. Both are correct resume points.

The `Location` of a new upload is a path without scheme and host, so a TLS-terminating proxy cannot turn it into an `http` URL. tus clients resolve it against the endpoint.

Consume copies the complete part into the storage adapter and generates variants synchronously. On a local adapter this is a second full write, so reserve enough free space for it. The internal completed file is not a PHP upload; listeners calling `moveTo()` in a web request cannot use `move_uploaded_file()` successfully.

Parallel chunks, per-chunk checksums, deferred lengths, and creation with upload are unsupported. Resume with the same file bytes. A declared `sha256` catches a mismatch at completion; without it, the server cannot detect that the client resumed from different content.
