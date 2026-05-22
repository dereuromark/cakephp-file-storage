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

The shipped migration creates `file_storage` with the following column types:

| Column | Default type | Purpose |
|--------|--------------|---------|
| `id` | `CHAR(36)` | The file row's own id (a UUID — always stays CHAR(36)). |
| `foreign_key` | `integer` | The owning record's id (your `Users.id`, `Posts.id`, …). Configurable via `Polymorphic.type`. |
| `user_id` | `integer` | Optional uploader / owner id — a plain integer FK to the app's users table. |

`user_id` and `foreign_key` both follow your application's primary-key signedness
(via the `Migrations.unsigned_primary_keys` flag — signed by default) for integer
variants. The `id` always stays a `CHAR(36)` UUID (the storage library's file
identity).

#### Configuring the foreign_key type

The `foreign_key` column type is controlled by the global `Polymorphic.type`
config key (default `'integer'`). Accepted values:

| Value | Column type | Signedness |
|-------|-------------|------------|
| `'integer'` (default) | `INTEGER` | Follows `Migrations.unsigned_primary_keys` |
| `'biginteger'` | `BIGINT` | Follows `Migrations.unsigned_primary_keys` |
| `'uuid'` | `CHAR(36)` | No signed option (not applicable) |
| `'binaryuuid'` | `BINARY(16)` | No signed option (not applicable) |

To use UUID foreign keys, set the config before running migrations:

```php
// config/app.php or config/app_local.php
Configure::write('Polymorphic.type', 'uuid'); // or 'binaryuuid'
```

::: tip Why keep the id as CHAR(36)?
The `file_storage.id` is the *file row's own* identifier, generated as a UUID by
the plugin's storage / path layer (don't pre-fill it — let the table assign it).
It is **not** a reference to one of your records. Only `foreign_key` points at
*your* polymorphic host records; `user_id` is a concrete integer FK to the users
table.
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
