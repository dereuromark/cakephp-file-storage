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
    return $fileStorage->user_id !== null
        && (string)$fileStorage->user_id === (string)$user->getIdentifier();
}
```

## Pattern 2: related-entity access

Control access based on a related entity: for example, an image that belongs to
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
    return $user !== null && $user->can('view', $fileStorage);
}
```

The authenticated identity must implement `Authorization\IdentityInterface`,
as configured by the Authorization plugin. If your middleware requires an
explicit authorization check, use the Authorization component's
`authorize($fileStorage, 'view')` in the action. Register a policy mapping for
`FileStorage\Model\Entity\FileStorage` to your application policy in the
`MapResolver` used by your `AuthorizationService`; a plugin entity does not
automatically resolve to an app policy.

Then define that policy:

```php
// src/Policy/FileStoragePolicy.php
namespace App\Policy;

class FileStoragePolicy
{
    public function canView($user, $fileStorage): bool
    {
        return $fileStorage->user_id !== null
            && (string)$fileStorage->user_id === (string)$user->getIdentifier();
    }
}
```

## Combining signatures with ownership checks

Use the controller from [Serving files](./) and replace its access check with
the signature-verification block in [Signed URLs](./signed-urls#verifying-signatures-manually).
After that check, serve the row's stored path through its adapter. A signature
is a bearer credential, so issue it only after checking access to the file.

## Testing authorization

Test your own controller with an authenticated owner, another user and a guest.
Use one HTTP request per integration test method. Assert that unauthorized
requests return 403 and cannot read bytes. Also test expired and tampered signed
URLs, missing storage files and your policy mapping. Authentication session
keys depend on your application's authentication setup.

The plugin does not dispatch a `FileStorage.beforeServe` event. Test the access
check or controller you actually implement, rather than an invented listener.

## See also

- [Signed URLs](./signed-urls): token-based temporary access.
- [Security and performance](./security): hardening and caching.
