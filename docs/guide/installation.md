# Installation

## Using Composer

Installing the plugin via [Composer](https://getcomposer.org/) is straightforward.
Run this in your project folder:

```bash
composer require dereuromark/cakephp-file-storage
```

## Load the plugin

```bash
bin/cake plugin load FileStorage
```

If you do not have authentication set up around the admin backend, disable its
routes so visitors cannot browse to the file-storage backend:

```bash
bin/cake plugin load FileStorage --no-routes
```

The admin backend is optional — you can always replace it with your own. See the
[Admin Backend](/admin/) page for how to gate and configure it.

## Database setup

Set up the plugin database using the
[official migrations plugin for CakePHP](https://github.com/cakephp/migrations):

```bash
bin/cake migrations migrate -p FileStorage
```

You can also copy the migrations over and adjust them to your needs, then run
your local `migrations migrate` instead.

### Foreign key column types

The shipped migration creates `file_storage` with **integer foreign keys** that
follow your application's primary-key signedness (via the
`Migrations.unsigned_primary_keys` flag — signed by default), while the `id`
stays a `CHAR(36)` UUID:

| Column | Default type | Purpose |
|--------|--------------|---------|
| `id` | `CHAR(36)` | The file row's own id (a UUID — keep as-is, see below). |
| `foreign_key` | `BIGINTEGER` | The owning record's id (your `Users.id`, `Posts.id`, …). |
| `user_id` | `BIGINTEGER` | Optional uploader / owner id. |

This matches the common case where your records use integer / auto-increment
primary keys (the CakePHP default), so `foreign_key` and `user_id` line up with
your integer-keyed tables and the signedness matches their primary keys.

#### Using UUID foreign keys

If the records your files belong to use **UUID primary keys**, copy the migration
into your app and change those two columns to `char` (keep everything else,
including `id`):

```php
$this->table('file_storage', ['id' => false, 'primary_key' => 'id'])
    ->addColumn('id', 'char', ['limit' => 36])
    ->addColumn('user_id', 'char', ['limit' => 36, 'null' => true, 'default' => null])
    ->addColumn('foreign_key', 'char', ['limit' => 36, 'null' => true, 'default' => null])
    // … all remaining columns unchanged
    ->create();
```

::: tip Why keep the id as CHAR(36)?
The `file_storage.id` is the *file row's own* identifier, generated as a UUID by
the plugin's storage / path layer (don't pre-fill it — let the table assign it).
It is **not** a reference to one of your records. Only `foreign_key` / `user_id`
point at *your* tables, so only those default to integer to match the typical app.
:::

## Adapter-specific configuration

Depending on your storage backend — for example Amazon S3 or Azure — you will
very likely need additional vendor libraries and extended adapter
configuration.

The plugin uses the FlySystem-based storage abstraction from the
`php-collective/file-storage` library. Check that library's documentation for
the available adapters and their configuration options.

::: tip
For local development the bundled `Local` adapter needs nothing extra. See
[Usage](./usage#configure-storage-service) for a minimal storage configuration.
:::

## Running the tests

The plugin tests are set up so you can run them without putting the plugin into
a CakePHP application. Go into the FileStorage folder and run:

```bash
cd <file-storage-plugin-folder>
composer update
composer test
```

Or run the tests directly with PHPUnit:

```bash
vendor/bin/phpunit
```

## Next steps

- [Quick Start](./quick-start) — a complete avatar upload walkthrough.
- [Usage](./usage) — storage configuration, associations, and the upload flow.
