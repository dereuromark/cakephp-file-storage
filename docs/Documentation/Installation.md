Installation
============

Using Composer
--------------

Installing the plugin via [Composer](https://getcomposer.org/) is very simple, just run in your project folder:

```
composer require dereuromark/cakephp-file-storage
```

## Load Plugin
Run
```
bin/cake plugin load FileStorage
```

Make sure to disable routing if you do not have authentication set up:
```
bin/cake plugin load FileStorage --no-routes
```
You do not want visitors to be able to browse to the file storage backend.

This backend is also optional, you can always replace it with your own.]()



Database Setup
--------------

You need to set up the plugin database using [the official migrations plugin for CakePHP](https://github.com/cakephp/migrations).

```
cake migrations migrate -p FileStorage
```

You can also copy over the migrations and manually adjust them to your needs and run local `migrations migrate` instead.


Adapter Specific Configuration
------------------------------

Depending on the storage backend of your choice, for example Amazon S3 or Azure, you'll very likely need additional vendor libs and extended adapter configuration.

The plugin uses the FlySystem-based storage abstraction from the `php-collective/file-storage` library. Check the documentation of that library for available adapters and configuration options.


## Setting up backend auth

The plugin's `Admin/FileStorageController` is **fail-closed by default**: every action throws
`ForbiddenException` until you explicitly opt in via the `FileStorage.adminAccess` config key.
This guarantees that misrouting / forgotten-middleware mistakes return 403 instead of silently
exposing list / view / edit / delete on the file storage table.

Pick one of the following depending on your auth stack.

### Option A — upstream gate (Authentication+Authorization plugin, TinyAuth, custom middleware)

Configure your stack to gate the `Admin` prefix the way you normally would, then tell the plugin
to trust that gate:

```php
// config/app.php (or any bootstrap file)
'FileStorage' => [
    'adminAccess' => true,
    // … rest of your FileStorage config
],
```

For TinyAuth specifically the per-controller ACL line stays the same:
```ini
[FileStorage.Admin/FileStorage]
* = admin
```
With `admin` being the role that should be able to access `/admin/file-storage`.

### Option B — inline closure (no auth plugin needed)

If you don't have a full auth stack and just want to gate by identity / role inline:

```php
'FileStorage' => [
    'adminAccess' => function (\Cake\Http\ServerRequest $request): bool {
        $identity = $request->getAttribute('identity');

        return $identity !== null && $identity->is_admin === true;
    },
    // …
],
```

The closure receives the current `ServerRequest` and must return `true` to allow access.
Anything else (false, falsy values, exceptions) is treated as deny.

### If you don't want the admin UI at all

Either skip loading the plugin's routes, or load it without routes:

```bash
bin/cake plugin load FileStorage --no-routes
```

WARNING: Do not flip `adminAccess` to `true` unless you actually have an upstream gate in place.
You do not want to make the uploaded content's metadata listable / editable publicly.

Running Tests
-------------

The plugin tests are set up in a way that you can run them without putting the plugin into a CakePHP application. All you need to do is to go into the FileStorage folder and run these commands:

```bash
cd <file-storage-plugin-folder>
composer update
composer test
```

Or run tests directly with PHPUnit:

```bash
vendor/bin/phpunit
```
