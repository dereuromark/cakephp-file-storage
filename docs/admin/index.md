# Admin Backend

The plugin ships a self-contained admin backend under the plugin's `Admin`
prefix — a dashboard, a file listing with bulk delete, and a storage cleanup UI.

::: danger Fail-closed by default
The `Admin/FileStorageController` is **fail-closed**: every action throws
`ForbiddenException` until you explicitly opt in via the `FileStorage.adminAccess`
config key. This guarantees that misrouting or forgotten-middleware mistakes
return 403 instead of silently exposing list / view / edit / delete on the file
storage table.
:::

## Setting up backend auth

Pick one of the following depending on your auth stack.

### Option A — upstream gate

For an Authentication + Authorization stack, TinyAuth, or custom middleware:
configure your stack to gate the `Admin` prefix the way you normally would, then
tell the plugin to trust that gate:

```php
// config/app.php (or any bootstrap file)
'FileStorage' => [
    'adminAccess' => true,
    // … rest of your FileStorage config
],
```

For TinyAuth specifically, the per-controller ACL line stays the same:

```ini
[FileStorage.Admin/FileStorage]
* = admin
```

…with `admin` being the role that should be able to access `/admin/file-storage`.

### Option B — inline closure

If you don't have a full auth stack and just want to gate by identity / role
inline:

```php
'FileStorage' => [
    'adminAccess' => function (\Cake\Http\ServerRequest $request): bool {
        $identity = $request->getAttribute('identity');

        return $identity !== null && $identity->is_admin === true;
    },
],
```

The closure receives the current `ServerRequest` and must return `true` to allow
access. Anything else (false, falsy values, exceptions) is treated as deny.

### If you don't want the admin UI at all

Either skip loading the plugin's routes, or load it without routes:

```bash
bin/cake plugin load FileStorage --no-routes
```

::: warning
Do not flip `adminAccess` to `true` unless you actually have an upstream gate in
place. You do not want to make the uploaded content's metadata listable /
editable publicly.
:::

## Available actions

Once `adminAccess` is set, the backend exposes:

| URL | Action |
|-----|--------|
| `/admin/file-storage` | Dashboard — counts, total size, top collections/models, recent uploads. |
| `/admin/file-storage/files` | File listing with bulk delete and (optional) variant regen. |
| `/admin/file-storage/cleanup` | Storage-tree cleanup UI — dry-run preview, then confirm. |
| `/admin/file-storage/dashboard` | Alias for the dashboard. |
| `/admin/file-storage/files/view/{id}` | View a single `file_storage` entity. |
| `/admin/file-storage/files/edit/{id}` | Edit a single `file_storage` entity. |

## Layout

The plugin ships its own Bootstrap 5 + Font Awesome 6 layout (loaded via CDN with
SRI — no webroot bundling). Two config keys control how it integrates into your
application:

```php
'FileStorage' => [
    'adminAccess' => true,

    // Layout switch:
    //   null        (default) — use the bundled `FileStorage.file_storage` layout
    //   false                 — fall back to your host app's default layout
    //   'App.admin'           — use any custom layout you ship
    'adminLayout' => null,

    // Standalone mode: when true, the admin controllers do NOT call
    // App\Controller\AppController::initialize() — only Flash is loaded.
    // Useful for apps without their own admin shell. Default: false (inherit).
    'standalone' => false,
],
```

If you want the admin UI to render inside *your* admin shell, set
`'adminLayout' => false`.

You can also add a "back to app" link in the admin header with `adminBackUrl`
(and an optional `adminBackLabel`); see the
[Configuration reference](/reference/#admin).

## Cleanup

The admin's **Cleanup** action runs the same logic as the
`bin/cake file_storage cleanup` CLI — both delegate to
`FileStorage\Service\CleanupService`. Use the UI for a dry-run preview (orphan
rows, orphan files on disk, missing backing files), then confirm to run. Use the
CLI for cron-driven runs.

## Variant regeneration (optional, requires Queue)

The file listing has a per-row "regenerate variants" button. It's enabled when
[dereuromark/cakephp-queue](https://github.com/dereuromark/cakephp-queue) is
installed and loaded; it enqueues a `Queue.Execute` job that runs
`bin/cake file_storage generate_image_variant …` on a worker (no synchronous
regen — long requests would 502).

Without the Queue plugin loaded the button is rendered disabled with a tooltip
pointing to the docs. Install it with:

```bash
composer require dereuromark/cakephp-queue
bin/cake plugin load Queue
```

See [The variant command](/images/command#background-regeneration-via-cakephp-queue)
for the background-processing details.
