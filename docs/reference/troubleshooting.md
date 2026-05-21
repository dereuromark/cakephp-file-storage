# Troubleshooting

## Uploads

### File not uploading

1. Check that your form has `'type' => 'file'`.
2. Verify the field is named `*.file` (e.g. `cover_image.file` for `hasOne`,
   `gallery_images.0.file` for `hasMany`).
3. Ensure `model`, `collection`, and `adapter` are set on the entity.
4. Check file permissions on the upload directory.

### Images not processing

1. Verify the GD or Imagick extension is installed.
2. Check that `fileProcessor` is configured in the behavior config.
3. Ensure image variants are configured for your Model/Collection combination.
4. Check the error logs for processing errors.

### Association not working

1. Verify `foreignKey` is set to `'foreign_key'`.
2. Check that `conditions` include both `model` and `collection`.
3. Ensure you use `contain` when loading entities.
4. Verify the entity `$_accessible` includes the association field.

## Serving

### Files always return 403

Your authorization logic in `checkAccess()` is denying the request. Add temporary
logging to see why:

```php
use Cake\Log\Log;

protected function checkAccess($fileStorage): bool
{
    Log::debug('Checking access for file: ' . $fileStorage->id);

    $user = $this->request->getAttribute('identity');
    Log::debug('User: ' . ($user ? $user->getIdentifier() : 'guest'));

    // … your authorization logic
}
```

### Routes not working

Verify your serving route is registered:

```bash
bin/cake routes | grep display
```

It should show your configured route, e.g. `/images/display/:id`.

### Signed URLs return 403

Possible causes:

1. **Clock skew** between servers.
2. **The file was modified** — the signature includes the modification time.
3. **Wrong signature secret.**

Verify a signature manually:

```php
use FileStorage\Utility\SignedUrlGenerator;
use Cake\Log\Log;

$isValid = SignedUrlGenerator::verify($fileStorage, $signature, [
    'expires' => $expires,
]);

Log::debug('Signature valid: ' . ($isValid ? 'yes' : 'no'));
```

## FAQ

**Can I serve files without going through a controller?**
For private files this is not recommended — direct file access bypasses
authorization. For public files, consider a CDN or `X-Accel-Redirect` /
`X-Sendfile`. For temporary token access, use the built-in
[signed-URL serving](/serving/signed-urls#built-in-signed-url-serving).

**How do I serve files from S3 or cloud storage?**
The storage adapter handles it. Your controller code stays the same — just
configure the adapter.

**Can I customize the response headers?**
Yes, in your serving controller:

```php
return $this->response
    ->withType($fileStorage->mime_type)
    ->withStringBody($contents)
    ->withHeader('X-Custom-Header', 'value');
```

**Do I need to use the URL helper?**
No, it's optional. You can build URLs manually — the helper just makes it easier
and configurable.
