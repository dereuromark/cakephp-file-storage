# Serving Files

The FileStorage plugin provides utilities for URL generation and signed URLs, and
ships a built-in signed-URL serving action. Applications implement their own
serving controller when they need custom authorization, audit logging or
response headers.

::: info Key principle
The plugin provides URL-generation tools and a signed-serving endpoint; your
application implements custom serving and access control where it needs to.
:::

For temporary, authentication-free access, the
[built-in signed-URL serving](./signed-urls#built-in-signed-url-serving) is the
recommended path: you don't need a controller at all. The rest of this section
covers building your own serving controller when you need app-specific
authorization.

## Quick start

### 1. Register the serving route

Add a route in your application's `config/routes.php`, inside its app scope:

```php
$routes->connect('/images/display/{id}', [
    'controller' => 'Images',
    'action' => 'display',
], ['pass' => ['id']]);
```

This example uses the integer database id. The built-in signed-download route
uses the UUID instead. There is no `FileStorage.serveRoute` configuration hook;
custom URLs must be generated with CakePHP's router.

### 2. Implement your serving controller

Create a controller to serve files with your authorization logic. This example
requires a server-assigned `user_id` on the file row. The avatar quick start
uses `foreign_key` instead, so check the owning user relation for that setup:

```php
// src/Controller/ImagesController.php
namespace App\Controller;

use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class ImagesController extends AppController
{
    /**
     * Serve a file with authorization.
     *
     * @param string|null $id File storage id
     * @return \Cake\Http\Response
     */
    public function display(?string $id = null): Response
    {
        if (!$id) {
            throw new NotFoundException('File ID required');
        }

        // Load the file storage record
        $fileStorageTable = $this->fetchTable('FileStorage.FileStorage');
        $fileStorage = $fileStorageTable->get($id);

        // YOUR AUTHORIZATION LOGIC HERE
        if (!$this->checkAccess($fileStorage)) {
            throw new ForbiddenException('Access denied');
        }

        // Get the storage adapter and read the file
        $behavior = $fileStorageTable->getBehavior('FileStorage');
        $adapter = $behavior->getStorageAdapter((string)$fileStorage->adapter);

        if (!$adapter->fileExists($fileStorage->path)) {
            throw new NotFoundException('File not found');
        }

        $contents = $adapter->read($fileStorage->path);

        return $this->response
            ->withType($fileStorage->mime_type)
            ->withStringBody($contents)
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Content-Security-Policy', "sandbox; default-src 'none'")
            ->withHeader('Content-Length', (string)$fileStorage->filesize)
            ->withDownload((string)$fileStorage->filename);
    }

    /**
     * Check whether the current user can access the file.
     *
     * @param \FileStorage\Model\Entity\FileStorage $fileStorage File storage entity
     * @return bool True if access is allowed
     */
    protected function checkAccess($fileStorage): bool
    {
        $identity = $this->request->getAttribute('identity');

        return $identity !== null && $fileStorage->user_id !== null
            && (string)$fileStorage->user_id === (string)$identity->getIdentifier();
    }
}
```

### 3. Generate URLs in templates

```php
use Cake\Routing\Router;

echo $this->Html->link(
    'View File',
    Router::url(['controller' => 'Images', 'action' => 'display', $fileStorage->id]),
);

// Generates a URL to your serving route, e.g. /images/display/{id}
```

## Next steps

- [Authorization](./authorization): ready-made access-control patterns.
- [Signed URLs](./signed-urls): temporary, token-based access.
- [Security and performance](./security): hardening, logging, and caching.
