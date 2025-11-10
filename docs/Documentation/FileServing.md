# File Serving

## Overview

The FileStorage plugin provides utilities for URL generation and signed URLs, but **does not include a serving controller**. Applications implement their own file serving based on their specific authorization requirements.

**Key Principle:** The plugin provides URL generation tools; your application implements serving and access control.

---

## Quick Start

### 1. Configure Serving Route

Tell the plugin where your serving controller is:

```php
// config/app.php
'FileStorage' => [
    'serveRoute' => [
        'controller' => 'Images',
        'action' => 'display',
        'plugin' => false,
    ],
    'signatureSecret' => env('FILE_STORAGE_SECRET'),
],
```

### 2. Implement Your Serving Controller

Create a controller to serve files with your authorization logic:

```php
// src/Controller/ImagesController.php
namespace App\Controller;

use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class ImagesController extends AppController
{
    /**
     * Serve a file with authorization
     *
     * @param string|null $id File storage ID
     * @return \Cake\Http\Response
     */
    public function display(?string $id = null): Response
    {
        if (!$id) {
            throw new NotFoundException('File ID required');
        }

        // Load file storage record
        $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
        $fileStorage = $fileStorageTable->get($id);

        // YOUR AUTHORIZATION LOGIC HERE
        if (!$this->_checkAccess($fileStorage)) {
            throw new ForbiddenException('Access denied');
        }

        // Get storage adapter and read file
        $behavior = $fileStorageTable->getBehavior('FileStorage');
        $adapter = $behavior->getStorageAdapter();

        if (!$adapter->has($fileStorage->path)) {
            throw new NotFoundException('File not found');
        }

        $contents = $adapter->read($fileStorage->path);

        // Return response
        return $this->response
            ->withType($fileStorage->mime_type)
            ->withStringBody($contents)
            ->withCache('-1 minute', '+1 year')
            ->withHeader('Content-Length', (string)$fileStorage->filesize)
            ->withHeader('Content-Disposition', 'inline; filename="' . $fileStorage->filename . '"');
    }

    /**
     * Check if user can access file
     *
     * @param \FileStorage\Model\Entity\FileStorage $fileStorage File storage entity
     * @return bool True if access allowed
     */
    protected function _checkAccess($fileStorage): bool
    {
        // Implement your authorization logic
        // Examples below in "Authorization Patterns" section
        return true;
    }
}
```

### 3. Generate URLs in Templates

```php
// Use the URL helper
$fileStorageTable = $this->fetchTable('FileStorage.FileStorage');

echo $this->Html->link('View File',
    $fileStorageTable->getUrl($fileStorage)
);

// This generates URL to your configured route:
// /images/display/{id}
```

---

## Authorization Patterns

### Pattern 1: Ownership-Based Access

Allow only file owners to access their files:

```php
// In ImagesController::_checkAccess()
protected function _checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');

    // Require authentication
    if (!$user) {
        return false;
    }

    // Check ownership
    return $fileStorage->user_id === $user->getIdentifier();
}
```

### Pattern 2: Related Entity Access (Albums Example)

Control access based on a related entity (e.g., image belongs to album with visibility settings):

```php
// In ImagesController::_checkAccess()
protected function _checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');

    // Load related entity (e.g., Image + Album)
    $imagesTable = $this->fetchTable('Images');
    $image = $imagesTable->find()
        ->where(['file_storage_id' => $fileStorage->id])
        ->contain(['Albums'])
        ->first();

    if (!$image || !$image->album) {
        return false;
    }

    $album = $image->album;

    // Owner always has access
    if ($user && $album->user_id === $user->getIdentifier()) {
        return true;
    }

    // Superadmin always has access
    if ($user && $this->AuthUser->hasRole(ROLE_SUPERADMIN)) {
        return true;
    }

    // Check foreign referer
    if ($this->Common->isForeignReferer()) {
        return false;
    }

    // Check visibility levels
    switch ($album->visibility) {
        case ALBUM_VIS_NONE:
            // Private - only owner/admin
            return false;

        case ALBUM_VIS_MEMBERS:
            // Members only
            return $user !== null;

        case ALBUM_VIS_ALL:
            // Public
            return true;

        default:
            return false;
    }
}
```

**This is the pattern for your current album system!**

### Pattern 3: Role-Based Access

Control access based on user roles:

```php
// In ImagesController::_checkAccess()
protected function _checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');

    if (!$user) {
        return false;
    }

    // Get required role from metadata
    $requiredRole = $fileStorage->metadata['required_role'] ?? 'member';

    // Check user has role
    return $this->hasRole($user, $requiredRole);
}

protected function hasRole($user, string $role): bool
{
    // Your role checking logic
    return in_array($role, $user->roles ?? []);
}
```

### Pattern 4: Time-Based Access

Control access based on time windows:

```php
// In ImagesController::_checkAccess()
protected function _checkAccess($fileStorage): bool
{
    $availableFrom = $fileStorage->metadata['available_from'] ?? null;
    $availableUntil = $fileStorage->metadata['available_until'] ?? null;

    $now = new FrozenTime();

    if ($availableFrom && $now < new FrozenTime($availableFrom)) {
        return false; // Not yet available
    }

    if ($availableUntil && $now > new FrozenTime($availableUntil)) {
        return false; // No longer available
    }

    return true;
}
```

### Pattern 5: Integration with CakePHP Authorization

If using `cakephp/authorization` plugin:

```php
// In ImagesController::_checkAccess()
protected function _checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');
    $authorization = $this->request->getAttribute('authorization');

    try {
        $authorization->authorize($user, 'view', $fileStorage);
        return true;
    } catch (ForbiddenException $e) {
        return false;
    }
}
```

Then define policy:

```php
// src/Policy/FileStoragePolicy.php
namespace App\Policy;

class FileStoragePolicy
{
    public function canView($user, $fileStorage): bool
    {
        // Your authorization logic
        return $fileStorage->user_id === $user->getIdentifier();
    }
}
```

---

---

## Signed URLs for Temporary Access

Signed URLs allow temporary access to files without authentication, useful for:
- Email attachments
- Share links
- API integrations
- Temporary downloads

### Generating Signed URLs

```php
use FileStorage\Utility\SignedUrlGenerator;

// In your controller
public function share($fileStorageId)
{
    $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
    $fileStorage = $fileStorageTable->get($fileStorageId);

    // Check user can share this file
    if (!$this->_checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    // Generate signature valid for 24 hours
    $signatureData = SignedUrlGenerator::generate($fileStorage, [
        'expires' => strtotime('+24 hours'),
    ]);

    // Generate full URL
    $url = $fileStorageTable->getUrl($fileStorage, [
        '?' => $signatureData,
        '_full' => true,
    ]);

    // Email or display URL
    $this->set('shareUrl', $url);
}
```

### Custom Expiration Times

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

### Custom Signature Secret

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

### Signature Verification

In your serving controller, check for signature in query string:

```php
public function display(?string $id = null): Response
{
    $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
    $fileStorage = $fileStorageTable->get($id);

    // Check for signed URL
    $signature = $this->request->getQuery('signature');
    $expires = $this->request->getQuery('expires');

    if ($signature && $expires) {
        // Verify signature
        $valid = SignedUrlGenerator::verify($fileStorage, $signature, [
            'expires' => (int)$expires,
        ]);

        if (!$valid) {
            throw new ForbiddenException('Invalid or expired signature');
        }

        // Signature valid - skip normal authorization
    } else {
        // Normal access - check authorization
        if (!$this->_checkAccess($fileStorage)) {
            throw new ForbiddenException('Access denied');
        }
    }

    // Serve file...
}
```

The signature includes:
- File storage ID
- File path
- File modification timestamp (invalidates on file change)
- Expiration timestamp

---

## Security Considerations

### 1. Always Implement Authorization

**CRITICAL:** Your serving controller MUST implement authorization. Without it, all files are publicly accessible.

```php
// ❌ INSECURE - No authorization
public function display($id = null) {
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);
    // Serves to anyone!
    return $this->response->withStringBody($adapter->read($fileStorage->path));
}

// ✅ SECURE - Proper authorization
public function display($id = null) {
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);

    if (!$this->_checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    return $this->response->withStringBody($adapter->read($fileStorage->path));
}
```

### 2. Validate File Relationships

Always verify the relationship between files and entities:

```php
// ❌ INSECURE - Trusts foreign_key
$imageId = $fileStorage->foreign_key;
$image = $this->Images->get($imageId);

// ✅ SECURE - Verifies relationship exists
$image = $this->Images->find()
    ->where([
        'Images.file_storage_id' => $fileStorage->id,
        'Images.id' => $fileStorage->foreign_key,
    ])
    ->first();

if (!$image) {
    // Relationship broken - deny access
}
```

### 3. Prevent Hotlinking

Block external referrers from embedding your files:

```php
// In ImagesController::_checkAccess()
protected function _checkAccess($fileStorage): bool
{
    // Check referer first
    if ($this->_isForeignReferer()) {
        return false;
    }

    // Then check other authorization...
}

protected function _isForeignReferer(): bool
{
    $referer = $this->request->getHeaderLine('Referer');

    if (!$referer) {
        return false; // No referer is OK
    }

    $allowedHosts = [
        'yourdomain.com',
        'www.yourdomain.com',
    ];

    $refererHost = parse_url($referer, PHP_URL_HOST);

    return !in_array($refererHost, $allowedHosts);
}
```

### 4. Rate Limiting

Implement rate limiting to prevent abuse:

```php
use Cake\Cache\Cache;

// In ImagesController
public function display(?string $id = null): Response
{
    // Check rate limit before loading file
    if (!$this->_checkRateLimit()) {
        throw new ForbiddenException('Rate limit exceeded');
    }

    // Load and serve file...
}

protected function _checkRateLimit(): bool
{
    $user = $this->request->getAttribute('identity');
    $identifier = $user ? $user->getIdentifier() : $this->request->clientIp();
    $cacheKey = 'file_access_' . md5($identifier);

    $count = Cache::read($cacheKey, 'file_rate_limit') ?: 0;

    if ($count > 100) { // 100 files per hour
        return false;
    }

    // Increment counter (expires in 1 hour)
    Cache::write($cacheKey, $count + 1, 'file_rate_limit');

    return true;
}
```

Configure cache:
```php
// config/app.php
'Cache' => [
    'file_rate_limit' => [
        'className' => 'File',
        'duration' => '+1 hour',
    ],
],
```

### 5. File Type Restrictions

Restrict which MIME types can be served:

```php
// In ImagesController
protected array $allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'video/mp4',
];

protected function _checkAccess($fileStorage): bool
{
    // Check MIME type first
    if (!in_array($fileStorage->mime_type, $this->allowedMimeTypes)) {
        return false;
    }

    // Then check other authorization...
}
```

### 6. Signed URL Rotation

For sensitive files, rotate signatures periodically:

```php
// Include additional data in signature that changes
$signature = SignedUrlGenerator::generate($fileStorage, [
    'expires' => strtotime('+1 hour'),
    'secret' => $this->getUserSecret($user), // User-specific secret
]);

// Invalidate all user's signed URLs by rotating their secret
protected function rotateUserSecret($userId): void
{
    $usersTable = $this->fetchTable('Users');
    $user = $usersTable->get($userId);
    $user->signature_secret = bin2hex(random_bytes(32));
    $usersTable->save($user);
}
```

---

## Access Logging

Track file access for auditing, analytics, or compliance:

```php
// In ImagesController::display()
public function display(?string $id = null): Response
{
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);

    if (!$this->_checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    // Log access
    $this->_logAccess($fileStorage);

    // Serve file...
}

protected function _logAccess($fileStorage): void
{
    $user = $this->request->getAttribute('identity');

    $this->fetchTable('FileAccessLogs')->createLog([
        'file_storage_id' => $fileStorage->id,
        'user_id' => $user ? $user->getIdentifier() : null,
        'ip_address' => $this->request->clientIp(),
        'user_agent' => $this->request->getHeaderLine('User-Agent'),
        'referer' => $this->request->getHeaderLine('Referer'),
        'timestamp' => new FrozenTime(),
    ]);
}
```

Create the logs table:

```sql
CREATE TABLE file_access_logs (
    id CHAR(36) PRIMARY KEY,
    file_storage_id CHAR(36) NOT NULL,
    user_id CHAR(36),
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer TEXT,
    created DATETIME NOT NULL,
    INDEX (file_storage_id),
    INDEX (user_id),
    INDEX (created)
);
```

---

## Testing

### Testing Authorization

```php
// tests/TestCase/Event/FileAuthorizationListenerTest.php
namespace App\Test\TestCase\Event;

use App\Event\FileAuthorizationListener;
use Cake\Event\Event;
use Cake\TestSuite\TestCase;

class FileAuthorizationListenerTest extends TestCase
{
    public function testBlocksUnauthenticatedUsers(): void
    {
        $listener = new FileAuthorizationListener();

        $fileStorage = $this->createMock('FileStorage\Model\Entity\FileStorage');

        $event = new Event('FileStorage.beforeServe', $this, [
            'fileStorage' => $fileStorage,
            'user' => null,
        ]);

        $listener->authorizeAccess($event);

        $this->assertTrue($event->isStopped());
        $this->assertEquals('Authentication required', $event->getResult());
    }

    public function testAllowsFileOwner(): void
    {
        $listener = new FileAuthorizationListener();

        $fileStorage = $this->createMock('FileStorage\Model\Entity\FileStorage');
        $fileStorage->user_id = 'user-123';

        $user = $this->createMock('Authentication\IdentityInterface');
        $user->method('getIdentifier')->willReturn('user-123');

        $event = new Event('FileStorage.beforeServe', $this, [
            'fileStorage' => $fileStorage,
            'user' => $user,
        ]);

        $listener->authorizeAccess($event);

        $this->assertFalse($event->isStopped());
    }
}
```

### Integration Tests

```php
// tests/TestCase/Controller/FileStorageControllerTest.php
public function testServeBlocksUnauthorizedAccess(): void
{
    $this->enableRetainFlashMessages();

    $fileStorage = $this->FileStorage->newEntity([
        'filename' => 'private.jpg',
        'path' => 'private/file.jpg',
        'user_id' => 'owner-123',
    ]);
    $this->FileStorage->saveOrFail($fileStorage);

    // Login as different user
    $this->session(['Auth.id' => 'other-user']);

    $this->get([
        'plugin' => 'FileStorage',
        'controller' => 'FileStorage',
        'action' => 'serve',
        $fileStorage->id,
    ]);

    $this->assertResponseCode(403);
}
```

---

## Performance Optimization

### 1. Cache Authorization Decisions

For complex authorization, cache the results:

```php
use Cake\Cache\Cache;

protected function _checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');

    $cacheKey = sprintf(
        'auth_%s_%s',
        $user ? $user->getIdentifier() : 'guest',
        $fileStorage->id
    );

    return Cache::remember($cacheKey, function() use ($user, $fileStorage) {
        return $this->_performAuthorizationCheck($user, $fileStorage);
    }, 'authorization');
}

protected function _performAuthorizationCheck($user, $fileStorage): bool
{
    // Your expensive authorization logic
}
```

### 2. Eager Load Relationships

Load related data in one query:

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

### 3. Use CDN for Public Files

For public files, bypass PHP serving:

```php
public function display(?string $id = null): Response
{
    $fileStorage = $this->fetchTable('FileStorage.FileStorage')->get($id);

    // Public files - redirect to CDN
    if ($fileStorage->is_public) {
        return $this->redirect($this->_getCdnUrl($fileStorage));
    }

    // Private files - serve through PHP with authorization
    if (!$this->_checkAccess($fileStorage)) {
        throw new ForbiddenException();
    }

    // Serve file...
}
```

---

## Complete Example: Protected Image Gallery

```php
// src/Controller/ImagesController.php
namespace App\Controller;

use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class ImagesController extends AppController
{
    /**
     * Display/serve an image with album visibility authorization
     *
     * @param string|null $id File storage ID
     * @return \Cake\Http\Response
     */
    public function display(?string $id = null): Response
    {
        if (!$id) {
            throw new NotFoundException('Image ID required');
        }

        // Load file storage record
        $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
        $fileStorage = $fileStorageTable->get($id);

        // Check for signed URL
        $signature = $this->request->getQuery('signature');
        $expires = $this->request->getQuery('expires');

        if ($signature && $expires) {
            // Verify signature
            $valid = \FileStorage\Utility\SignedUrlGenerator::verify(
                $fileStorage,
                $signature,
                ['expires' => (int)$expires]
            );

            if (!$valid) {
                throw new ForbiddenException('Invalid or expired signature');
            }
            // Signature valid - skip normal authorization
        } else {
            // Normal access - check authorization
            if (!$this->_checkRights($fileStorage)) {
                $this->Flash->error('Kein Zugriff');
                throw new ForbiddenException('Access denied');
            }
        }

        // Get storage adapter and read file
        $behavior = $fileStorageTable->getBehavior('FileStorage');
        $adapter = $behavior->getStorageAdapter();

        if (!$adapter->has($fileStorage->path)) {
            throw new NotFoundException('File not found');
        }

        $contents = $adapter->read($fileStorage->path);

        // Increment view counter
        $this->_incrementViewCounter($fileStorage);

        // Return response
        return $this->response
            ->withType($fileStorage->mime_type)
            ->withStringBody($contents)
            ->withCache('-1 minute', '+1 year')
            ->withHeader('Content-Length', (string)$fileStorage->filesize)
            ->withHeader('Content-Disposition', 'inline; filename="' . $fileStorage->filename . '"')
            ->withHeader('Accept-Ranges', 'bytes');
    }

    /**
     * Check if user has rights to view image based on album visibility
     *
     * @param \FileStorage\Model\Entity\FileStorage $fileStorage File storage entity
     * @return bool True if access allowed
     */
    protected function _checkRights($fileStorage): bool
    {
        $user = $this->request->getAttribute('identity');

        // Block hotlinking
        if ($this->_isForeignReferer()) {
            return false;
        }

        // Load image with album
        $imagesTable = $this->fetchTable('Images');
        $image = $imagesTable->find()
            ->where(['file_storage_id' => $fileStorage->id])
            ->contain(['Albums'])
            ->first();

        if (!$image || !$image->album) {
            return false;
        }

        $album = $image->album;

        // Owner always allowed
        if ($user && $album->user_id === $user->getIdentifier()) {
            return true;
        }

        // Superadmin always allowed
        if ($user && $this->AuthUser->hasRole(ROLE_SUPERADMIN)) {
            return true;
        }

        // Check visibility
        switch ($album->visibility) {
            case ALBUM_VIS_NONE:
                // Private - only owner/admin
                return false;

            case ALBUM_VIS_MEMBERS:
                // Members only
                return $user !== null;

            case ALBUM_VIS_ALL:
                // Public
                return true;

            default:
                return false;
        }
    }

    /**
     * Check if referer is from external domain
     *
     * @return bool True if foreign referer
     */
    protected function _isForeignReferer(): bool
    {
        $referer = $this->request->getHeaderLine('Referer');

        if (!$referer) {
            return false; // No referer is OK
        }

        $allowedHost = $this->request->host();
        $refererHost = parse_url($referer, PHP_URL_HOST);

        return $refererHost !== $allowedHost;
    }

    /**
     * Increment view counter for image
     *
     * @param \FileStorage\Model\Entity\FileStorage $fileStorage File storage entity
     * @return void
     */
    protected function _incrementViewCounter($fileStorage): void
    {
        $imagesTable = $this->fetchTable('Images');
        $imagesTable->updateAll(
            ['count = count + 1'],
            ['file_storage_id' => $fileStorage->id]
        );
    }
}
```

**This is a complete, production-ready implementation matching your album visibility requirements!**

---

## Troubleshooting

### Files Always Return 403

**Problem:** All file requests return "Access denied"

**Solution:** Check your authorization logic in `_checkAccess()`:

```php
protected function _checkAccess($fileStorage): bool
{
    Log::debug('Checking access for file: ' . $fileStorage->id);

    // Debug your authorization logic
    $user = $this->request->getAttribute('identity');
    Log::debug('User: ' . ($user ? $user->getIdentifier() : 'guest'));

    // Your authorization logic...
}
```

### Routes Not Working

**Problem:** Cannot access serving controller

**Solution:** Verify your routes are configured:

```bash
bin/cake routes | grep display
```

Should show your configured route:
```
/images/display/:id
```

### Signed URLs Return 403

**Problem:** Valid signed URLs are rejected

**Possible causes:**
1. Clock skew between servers
2. File was modified (signature includes modification time)
3. Wrong signature secret

**Debug:**
```php
use FileStorage\Utility\SignedUrlGenerator;

// Verify signature manually
$isValid = SignedUrlGenerator::verify($fileStorage, $signature, [
    'expires' => $expires,
]);

Log::debug('Signature valid: ' . ($isValid ? 'yes' : 'no'));
```

---

## FAQ

**Q: Can I serve files without going through a controller?**
A: Not recommended for private files. Direct file access bypasses authorization. For public files, consider using a CDN or X-Accel-Redirect/X-Sendfile.

**Q: How do I serve files from S3 or cloud storage?**
A: The storage adapter automatically handles it. Your controller code stays the same - just configure the adapter.

**Q: Can I customize the response headers?**
A: Yes, in your serving controller:

```php
public function display($id = null): Response
{
    // ... load and authorize file ...

    return $this->response
        ->withType($fileStorage->mime_type)
        ->withStringBody($contents)
        ->withHeader('X-Custom-Header', 'value');
}
```

**Q: How do I implement download quotas?**
A: Track downloads in the `afterServe` event and check quota in `beforeServe`.

**Q: Do I need to use the URL helper?**
A: No, it's optional. You can build URLs manually if you prefer. The helper just makes it easier and configurable.

---

For more examples, see the [FileStorage plugin repository](https://github.com/dereuromark/cakephp-file-storage).
