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

::: warning Foreign keys default to UUID (CHAR(36)), not integers
The shipped migration creates `file_storage` with a UUID primary key **and**
UUID-shaped foreign-key columns:

| Column | Default type | Purpose |
|--------|--------------|---------|
| `id` | `CHAR(36)` | The file row's own id (a UUID — keep as-is, see below). |
| `foreign_key` | `CHAR(36)` | The owning record's id (your `Users.id`, `Posts.id`, …). |
| `user_id` | `CHAR(36)` | Optional uploader / owner id. |

If your app uses **integer / auto-increment primary keys** (the CakePHP
default), `CHAR(36)` is the wrong type for `foreign_key` and `user_id`: an
integer id like `42` is silently stored as the string `"42"`, the column is
oversized, and you cannot add a real DB foreign-key constraint or join cleanly
against your integer-keyed tables.
:::

**To use integer foreign keys**, copy the migration into your app and change
those two columns (keep everything else, including `id`):

```php
$this->table('file_storage', ['id' => false, 'primary_key' => 'id'])
    ->addColumn('id', 'char', ['limit' => 36])                          // keep: see note below
    ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
    ->addColumn('foreign_key', 'integer', ['null' => true, 'default' => null])
    // … all remaining columns unchanged
    ->create();
```

Use `biginteger` instead of `integer` if your tables use big-integer keys.

::: tip Why keep the id as CHAR(36)?
The `file_storage.id` is the *file row's own* identifier, generated as a UUID by
the plugin's storage / path layer (don't pre-fill it — let the table assign it).
It is **not** a reference to one of your records. Only `foreign_key` / `user_id`
point at *your* tables, so only those need to match your app's key type.

A future major version may flip this default to integer foreign keys (UUID
becoming opt-in) — see [issue #37](https://github.com/dereuromark/cakephp-file-storage/issues/37).
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
