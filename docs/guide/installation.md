# Installation

This branch requires PHP 8.2+ and CakePHP 5.3.4+. The optional image processor
2.x requires PHP 8.3+; use processor 1.x on PHP 8.2.

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

The admin backend denies access until you configure `FileStorage.adminAccess`.
If you do not need any plugin routes, load it without routes:

```bash
bin/cake plugin load FileStorage --no-routes
```

This also disables the built-in signed-download route. Keep routes enabled if
you use `SignedUrlGenerator::url()`. The admin backend is optional. See the
[Admin Backend](/admin/) page for how to gate and configure it.

## Database setup

Set up the plugin database using the
[official migrations plugin for CakePHP](https://github.com/cakephp/migrations):

```bash
composer require cakephp/migrations
bin/cake plugin load Migrations
bin/cake migrations migrate -p FileStorage
```

Skip loading Migrations if it is already loaded. For an existing installation,
read [Upgrading](./upgrading) before applying the migration set.

You can also copy the migrations over and adjust them to your needs, then run
your local `migrations migrate` instead.

### Foreign key column types

The shipped migration creates `file_storage` with the following column types:

| Column | Default type | Purpose |
|--------|--------------|---------|
| `id` | `integer` | The file row's own database primary key. |
| `uuid` | `CHAR(36)` | The file row's public / storage identity. |
| `foreign_key` | `integer` | The owning record's id (your `Users.id`, `Posts.id`, …). Configurable via `Polymorphic.type`. |
| `user_id` | `integer` | Optional uploader / owner id: a plain integer FK to the app's users table. |

`user_id` and `foreign_key` both follow your application's primary-key signedness
(via the `Migrations.unsigned_primary_keys` flag: signed by default) for integer
variants. The `uuid` column stores the storage library's file identity.

#### Configuring the foreign_key type

The `foreign_key` column type is controlled by the global `Polymorphic.type`
config key (default `'integer'`). Accepted values:

| Value | Column type | Signedness |
|-------|-------------|------------|
| `'integer'` (default) | `INTEGER` | Follows `Migrations.unsigned_primary_keys` |
| `'biginteger'` | `BIGINT` | Follows `Migrations.unsigned_primary_keys` |
| `'uuid'` | `CHAR(36)` | No signed option (not applicable) |
| `'binaryuuid'` | `BINARY(16)` | No signed option (not applicable) |

To use UUID foreign keys, add the config key before running migrations on a *fresh
install*. Existing installs require a separate app-side migration to alter the
`foreign_key` column: changing this value after the initial migration has no
effect on an already-created column.

```php
// config/app.php (merged into Configure at bootstrap, including the migrations CLI)
'Polymorphic' => [
    'type' => 'uuid', // integer (default) | biginteger | uuid | binaryuuid
],
```

::: tip Upgrading from the UUID primary key schema
Older versions used `file_storage.id` as a `CHAR(36)` UUID. Version 5.0 moves
the public/storage identity into a dedicated `uuid` column and uses an integer
`id` for database references. The shipped conversion preserves old UUIDs but
assigns new integer row ids. Update any application-side references as part of
your migration. Adding `uuid` alone to a legacy UUID-primary-key table is not
a complete upgrade.

See the [upgrade guide](./upgrading) for the conversion and migration
checklist.
:::

## Adapter-specific configuration

Backends such as Amazon S3 or Azure need additional vendor libraries and extended adapter
configuration.

The plugin uses the FlySystem-based storage abstraction from the
`php-collective/file-storage` library. Check that library's documentation for
the available adapters and their configuration options.

::: tip
For local development the bundled `Local` adapter needs nothing extra. See
[Usage](./usage#_2-configure-the-storage-service) for a minimal storage configuration.
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

- [Quick Start](./quick-start): a complete avatar upload walkthrough.
- [Usage](./usage): storage configuration, associations, and the upload flow.
