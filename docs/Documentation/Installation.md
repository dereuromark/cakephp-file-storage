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

> [!IMPORTANT]
> **By default the foreign-key columns are `CHAR(36)` (UUID-shaped) — not integers.**
> The shipped migration creates `file_storage` with a UUID primary key **and**
> UUID-shaped foreign-key columns:
>
> | Column        | Default type | Purpose                                                    |
> |---------------|--------------|------------------------------------------------------------|
> | `id`          | `CHAR(36)`   | The file row's own id (a UUID, see below) — **keep as-is**  |
> | `foreign_key` | `CHAR(36)`   | The owning record's id (your `Users.id`, `Posts.id`, …)    |
> | `user_id`     | `CHAR(36)`   | Optional uploader/owner id                                  |
>
> If your app uses **integer / auto-increment primary keys** (the CakePHP default),
> `CHAR(36)` is the wrong type for `foreign_key` and `user_id`: an integer id like
> `42` is silently stored as the string `"42"`, the column is oversized, and you
> cannot add a real DB foreign-key constraint or join cleanly against your
> integer-keyed tables.
>
> **To use integer foreign keys, copy the migration into your app and change those
> two columns** (keep everything else, including `id`):
>
> ```php
> $this->table('file_storage', ['id' => false, 'primary_key' => 'id'])
>     ->addColumn('id', 'char', ['limit' => 36])                          // keep: see note below
>     ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
>     ->addColumn('foreign_key', 'integer', ['null' => true, 'default' => null])
>     // … all remaining columns unchanged
>     ->create();
> ```
>
> Use `biginteger` instead of `integer` if your tables use big-integer keys.
>
> **Why keep `id` as `CHAR(36)`?** The `file_storage.id` is the *file row's own*
> identifier, generated as a UUID by the plugin's storage/path layer (don't
> pre-fill it — let the table assign it). It is **not** a reference to one of your
> records. Only `foreign_key` / `user_id` point at *your* tables, so only those
> need to match your app's key type.
>
> A future major version may flip this default to integer foreign keys (UUID
> becoming opt-in) — see [#37](https://github.com/dereuromark/cakephp-file-storage/issues/37).


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

## The admin backend

Once `adminAccess` is set, the plugin exposes a self-contained admin backend
under the plugin's `Admin` prefix:

| URL                             | Action                                                        |
|---------------------------------|---------------------------------------------------------------|
| `/admin/file-storage`           | Dashboard — counts, total size, top collections/models, recent uploads |
| `/admin/file-storage/files`     | File listing with bulk delete and (optional) variant regen   |
| `/admin/file-storage/cleanup`   | Storage-tree cleanup UI — dry-run preview, then confirm      |
| `/admin/file-storage/dashboard` | Alias for the dashboard                                       |
| `/admin/file-storage/files/view/{id}` | View a single file_storage entity                       |
| `/admin/file-storage/files/edit/{id}` | Edit a single file_storage entity                       |

### Layout

The plugin ships its own Bootstrap 5 + Font Awesome 6 layout (loaded via CDN
with SRI — no webroot bundling). Two config keys control how it integrates
into your application:

```php
'FileStorage' => [
    'adminAccess' => true,
    // Layout switch:
    //   null     (default) — use the bundled `FileStorage.file_storage` layout
    //   false              — fall back to your host app's default layout
    //   'App.admin'        — use any custom layout you ship
    'adminLayout' => null,

    // Standalone mode: when true, the admin controllers do NOT call
    // App\Controller\AppController::initialize() — only Flash is loaded.
    // Useful for apps without their own admin shell. Default: false (inherit).
    'standalone' => false,
],
```

If you want the admin UI to render inside *your* admin shell (matching the
pre-4.4 behavior), set `'adminLayout' => false`.

### Cleanup

The admin's **Cleanup** action is the same logic as the existing
`bin/cake file_storage cleanup` CLI — both delegate to
`FileStorage\Service\CleanupService`. Use the UI for a dry-run preview
(orphan rows, orphan files on disk, missing backing files), then confirm to
run. Use the CLI for cron-driven runs.

### Variant regeneration (optional, requires Queue)

The file listing has a per-row "regenerate variants" button. It's enabled
when [dereuromark/cakephp-queue](https://github.com/dereuromark/cakephp-queue)
is installed and loaded; it enqueues a `Queue.Execute` job that runs
`bin/cake file_storage generate_image_variant ...` on a worker (no
synchronous regen — long requests would 502).

Without the Queue plugin loaded the button is rendered disabled with a
tooltip pointing here. Install:

```
composer require dereuromark/cakephp-queue
bin/cake plugin load Queue
```

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
