# Security and Performance

This page collects the hardening, logging, and performance considerations for
serving files through your own controller.

## Always implement authorization

::: danger Critical
Your serving controller MUST implement authorization. Without it, all files are
publicly accessible.
:::

```php
// ❌ INSECURE: no authorization
public function display($id = null) {
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);
    // Serves to anyone!
    return $this->response->withStringBody($adapter->read($fileStorage->path));
}

// ✅ SECURE: proper authorization
public function display($id = null) {
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);

    if (!$this->checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    return $this->response->withStringBody($adapter->read($fileStorage->path));
}
```

## Validate file relationships

Always verify the relationship between files and entities:

```php
// ❌ INSECURE: trusts foreign_key blindly
$imageId = $fileStorage->foreign_key;
$image = $this->Images->get($imageId);

// ✅ SECURE: verifies the relationship exists
$image = $this->Images->find()
    ->where([
        'Images.file_storage_id' => $fileStorage->id,
        'Images.id' => $fileStorage->foreign_key,
    ])
    ->first();

if (!$image) {
    // Relationship broken: deny access
}
```

## Prevent hotlinking

Referer checks can discourage browser hotlinking. They are not authorization:
clients can omit or forge this header. Check access independently:

```php
protected function checkAccess($fileStorage): bool
{
    if ($this->isForeignReferer()) {
        return false;
    }

    // … then check other authorization
}

protected function isForeignReferer(): bool
{
    $referer = $this->request->getHeaderLine('Referer');
    if (!$referer) {
        return false; // no referer is OK
    }

    $allowedHosts = ['yourdomain.com', 'www.yourdomain.com'];
    $refererHost = parse_url($referer, PHP_URL_HOST);

    return !in_array($refererHost, $allowedHosts, true);
}
```

## Rate limiting

Use atomic counters or a reverse proxy for enforced rate limits. The following
cache example is an approximate per-key inactivity window: reads and writes can
race, and each write renews the cache TTL. It is not a fixed hourly quota:

```php
use Cake\Cache\Cache;

public function display(?string $id = null): Response
{
    if (!$this->checkRateLimit()) {
        throw new ForbiddenException('Rate limit exceeded');
    }

    // Load and serve the file …
}

protected function checkRateLimit(): bool
{
    $user = $this->request->getAttribute('identity');
    $identifier = $user ? $user->getIdentifier() : $this->request->clientIp();
    $cacheKey = 'file_access_' . md5($identifier);

    $count = Cache::read($cacheKey, 'file_rate_limit') ?: 0;

    if ($count >= 100) { // Approximate per-key limit.
        return false;
    }

    Cache::write($cacheKey, $count + 1, 'file_rate_limit');

    return true;
}
```

Configure the cache:

```php
// config/app.php
'Cache' => [
    'file_rate_limit' => [
        'className' => 'File',
        'duration' => '+1 hour',
    ],
],
```

## File-type restrictions

Restrict which MIME types can be served. Populate the stored MIME type from
server-side content detection before relying on this check. For arbitrary
documents, prefer attachment delivery with a sandbox CSP, as in the
[serving controller](./):

```php
protected array $allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'video/mp4',
];

protected function checkAccess($fileStorage): bool
{
    if (!in_array($fileStorage->mime_type, $this->allowedMimeTypes, true)) {
        return false;
    }

    // … then check other authorization
}
```

## Signed-URL rotation

For a custom serving controller, generate and verify with a per-user secret.
The built-in signed action does not resolve per-user secrets:

```php
$signature = SignedUrlGenerator::generate($fileStorage, [
    'expires' => strtotime('+1 hour'),
    'secret' => $this->getUserSecret($user), // user-specific secret
]);

// In your custom controller, verify with this same per-user secret.
// Invalidate that user's signed URLs by rotating it.
protected function rotateUserSecret($userId): void
{
    $usersTable = $this->fetchTable('Users');
    $user = $usersTable->get($userId);
    $user->signature_secret = bin2hex(random_bytes(32));
    $usersTable->save($user);
}
```

## Access logging

Track file access for auditing, analytics, or compliance:

```php
public function display(?string $id = null): Response
{
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);

    if (!$this->checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    $this->logAccess($fileStorage);

    // Serve the file …
}

protected function logAccess($fileStorage): void
{
    $user = $this->request->getAttribute('identity');

    $this->fetchTable('FileAccessLogs')->createLog([
        'file_storage_id' => $fileStorage->id,
        'user_id' => $user ? $user->getIdentifier() : null,
        'ip_address' => $this->request->clientIp(),
        'user_agent' => $this->request->getHeaderLine('User-Agent'),
        'referer' => $this->request->getHeaderLine('Referer'),
    ]);
}
```

## Performance optimization

### Cache authorization decisions

For expensive authorization, cache the results:

```php
use Cake\Cache\Cache;

protected function checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');

    $cacheKey = sprintf(
        'auth_%s_%s',
        $user ? $user->getIdentifier() : 'guest',
        $fileStorage->id,
    );

    return Cache::remember($cacheKey, function () use ($user, $fileStorage) {
        return $this->performAuthorizationCheck($user, $fileStorage);
    }, 'authorization');
}
```

### Eager-load relationships

Define the application associations first, then eager-load related data to avoid
issuing a query per row. These `Images` and `Albums` associations are app-specific:

```php
// ❌ N+1 problem
foreach ($fileStorages as $fileStorage) {
    $this->Images->find()->where(['file_storage_id' => $fileStorage->id])->first();
}

// ✅ Eager loading
$fileStorages = $this->FileStorage->find()
    ->matching('Images.Albums')
    ->all();
```

### Use a CDN for public files

For public files, bypass PHP serving:

```php
public function display(?string $id = null): Response
{
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);

    // Public files: redirect to the CDN
    if ($fileStorage->is_public) {
        return $this->redirect($this->getCdnUrl($fileStorage));
    }

    // Private files: serve through PHP with authorization
    if (!$this->checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    // Serve the file …
}
```

## See also

- [Troubleshooting](/reference/troubleshooting): debugging 403s and signed-URL issues.
- [Authorization](./authorization): access-control patterns.
