# Signed URLs

Signed URLs allow temporary access to files without authentication. They are
useful for:

- email attachments
- share links
- API integrations
- temporary downloads

## Built-in signed-URL serving

::: tip Recommended
This is the simplest path — no serving controller required.
:::

The plugin ships a public action at `/file-storage/signed/{uuid}/{signature}` that
verifies the signature, looks up the file, and streams it directly. For
local-filesystem adapters it uses `Response::withFile()`, giving HTTP `Range`
support for `<video>` and `<audio>` elements for free; non-local adapters fall
back to a single `read()` plus string body.

Generate the URL with the one-call helper:

```php
use FileStorage\Utility\SignedUrlGenerator;

$url = SignedUrlGenerator::url($fileStorage, [
    'expires' => strtotime('+24 hours'),
    'fullBase' => true, // absolute URL for email
]);
// → http://example.com/file-storage/signed/<uuid>/<sha256>?expires=1799999999
```

The helper places the signature in the path segment (so reverse-proxy access
logs that scrub query strings don't shred half the credential) and `expires` in
the query string. The matching action handles:

- **404** for an unknown UUID;
- **403** for tampered or expired signatures (same status either way, so probing
  callers can't distinguish);
- **404** if the backing file disappeared from the adapter.

If `Authentication` is loaded globally, the `signed` action calls
`allowUnauthenticated('signed')` on initialize so the request isn't bounced to
the login form — that's the entire authorization story for a signed URL.

## Custom serving controller

You can still ship your own controller when you need extra checks beyond
signature verification (rate limiting, audit logs, header tweaks). Generate the
signature with `SignedUrlGenerator::generate()` and route it however you like:

```php
use FileStorage\Utility\SignedUrlGenerator;

public function share($fileStorageId)
{
    $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
    $fileStorage = $fileStorageTable->get($fileStorageId);

    if (!$this->checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    $signatureData = SignedUrlGenerator::generate($fileStorage, [
        'expires' => strtotime('+24 hours'),
    ]);

    $url = Router::url([
        'controller' => 'Files',
        'action' => 'serve',
        $fileStorage->uuid,
        '?' => $signatureData,
        '_full' => true,
    ]);

    $this->set('shareUrl', $url);
}
```

## Custom expiration times

```php
// 1 hour
$signature = SignedUrlGenerator::generate($fileStorage, [
    'expires' => strtotime('+1 hour'),
]);

// 7 days
$signature = SignedUrlGenerator::generate($fileStorage, [
    'expires' => strtotime('+7 days'),
]);

// Specific date/time
$signature = SignedUrlGenerator::generate($fileStorage, [
    'expires' => strtotime('2025-12-31 23:59:59'),
]);
```

## Custom signature secret

For additional security, use a custom secret:

```php
// config/app.php
'FileStorage' => [
    'signatureSecret' => env('FILE_STORAGE_SECRET', 'your-secret-key'),
],
```

Generate a secure secret:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

::: info Default
When `signatureSecret` is unset, signing falls back to the application's
`Security.salt`. Set it explicitly to decouple signed-URL invalidation from the
salt.
:::

## Verifying signatures manually

In a custom serving controller, check for the signature in the query string:

```php
public function display(?string $id = null): Response
{
    $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
    $fileStorage = $fileStorageTable->get($id);

    $signature = $this->request->getQuery('signature');
    $expires = $this->request->getQuery('expires');

    if ($signature && $expires) {
        $valid = SignedUrlGenerator::verify($fileStorage, $signature, [
            'expires' => (int)$expires,
        ]);

        if (!$valid) {
            throw new ForbiddenException('Invalid or expired signature');
        }
        // Signature valid — skip normal authorization
    } elseif (!$this->checkAccess($fileStorage)) {
        // Normal access — check authorization
        throw new ForbiddenException('Access denied');
    }

    // Serve the file …
}
```

The signature includes the file storage id, the file path, the file modification
timestamp (so it invalidates on file change), and the expiration timestamp.

## See also

- [Authorization](./authorization) — access-control patterns.
- [Security and performance](./security#signed-url-rotation) — rotating
  signatures for sensitive files.
