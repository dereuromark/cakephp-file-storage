# Authorization

These are ready-made patterns for the `checkAccess()` method of your
[serving controller](./). Pick the one that matches your access model, or combine
several.

## Pattern 1: ownership-based access

Allow only file owners to access their files:

```php
protected function checkAccess($fileStorage): bool
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

## Pattern 2: related-entity access

Control access based on a related entity — for example, an image that belongs to
an album with visibility settings:

```php
protected function checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');

    // Load the related entity (e.g. Image + Album)
    $imagesTable = $this->fetchTable('Images');
    $image = $imagesTable->find()
        ->where(['file_storage_id' => $fileStorage->id])
        ->contain(['Albums'])
        ->first();

    if (!$image || !$image->album) {
        return false;
    }

    $album = $image->album;

    // The owner always has access
    if ($user && $album->user_id === $user->getIdentifier()) {
        return true;
    }

    // Block hotlinking from foreign referers
    if ($this->isForeignReferer()) {
        return false;
    }

    // Check visibility levels (your app's own constants)
    return match ($album->visibility) {
        'private' => false,        // only the owner
        'members' => $user !== null, // members only
        'public' => true,
        default => false,
    };
}
```

## Pattern 3: role-based access

Control access based on user roles:

```php
protected function checkAccess($fileStorage): bool
{
    $user = $this->request->getAttribute('identity');

    if (!$user) {
        return false;
    }

    // Get the required role from metadata
    $requiredRole = $fileStorage->metadata['required_role'] ?? 'member';

    return $this->hasRole($user, $requiredRole);
}

protected function hasRole($user, string $role): bool
{
    return in_array($role, $user->roles ?? [], true);
}
```

## Pattern 4: time-based access

Control access based on time windows:

```php
use Cake\I18n\DateTime;

protected function checkAccess($fileStorage): bool
{
    $availableFrom = $fileStorage->metadata['available_from'] ?? null;
    $availableUntil = $fileStorage->metadata['available_until'] ?? null;

    $now = new DateTime();

    if ($availableFrom && $now < new DateTime($availableFrom)) {
        return false; // not yet available
    }

    if ($availableUntil && $now > new DateTime($availableUntil)) {
        return false; // no longer available
    }

    return true;
}
```

## Pattern 5: integration with cakephp/authorization

If you use the [`cakephp/authorization`](https://github.com/cakephp/authorization)
plugin:

```php
protected function checkAccess($fileStorage): bool
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

Then define a policy:

```php
// src/Policy/FileStoragePolicy.php
namespace App\Policy;

class FileStoragePolicy
{
    public function canView($user, $fileStorage): bool
    {
        return $fileStorage->user_id === $user->getIdentifier();
    }
}
```

## Complete example: protected image gallery

A serving action that combines signed-URL verification with album-visibility
authorization:

```php
// src/Controller/ImagesController.php
namespace App\Controller;

use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use FileStorage\Utility\SignedUrlGenerator;

class ImagesController extends AppController
{
    public function display(?string $id = null): Response
    {
        if (!$id) {
            throw new NotFoundException('Image ID required');
        }

        $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
        $fileStorage = $fileStorageTable->get($id);

        // Check for a signed URL first
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
            throw new ForbiddenException('Access denied');
        }

        $behavior = $fileStorageTable->getBehavior('FileStorage');
        $adapter = $behavior->getStorageAdapter();

        if (!$adapter->has($fileStorage->path)) {
            throw new NotFoundException('File not found');
        }

        $contents = $adapter->read($fileStorage->path);

        return $this->response
            ->withType($fileStorage->mime_type)
            ->withStringBody($contents)
            ->withCache('-1 minute', '+1 year')
            ->withHeader('Content-Length', (string)$fileStorage->filesize)
            ->withHeader('Content-Disposition', 'inline; filename="' . $fileStorage->filename . '"')
            ->withHeader('Accept-Ranges', 'bytes');
    }

    protected function isForeignReferer(): bool
    {
        $referer = $this->request->getHeaderLine('Referer');
        if (!$referer) {
            return false; // no referer is OK
        }

        return parse_url($referer, PHP_URL_HOST) !== $this->request->host();
    }
}
```

## Testing authorization

Unit-test the access decision in isolation:

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
        $this->assertSame('Authentication required', $event->getResult());
    }
}
```

Integration-test the controller response:

```php
public function testServeBlocksUnauthorizedAccess(): void
{
    $fileStorage = $this->FileStorage->newEntity([
        'filename' => 'private.jpg',
        'path' => 'private/file.jpg',
        'user_id' => 'owner-123',
    ]);
    $this->FileStorage->saveOrFail($fileStorage);

    // Log in as a different user
    $this->session(['Auth.id' => 'other-user']);

    $this->get([
        'controller' => 'Images',
        'action' => 'display',
        $fileStorage->id,
    ]);

    $this->assertResponseCode(403);
}
```

## See also

- [Signed URLs](./signed-urls) — token-based temporary access.
- [Security and performance](./security) — hardening and caching.
