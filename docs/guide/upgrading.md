# Upgrading

This page covers the CakePHP 6 development branch and the earlier 5.x upgrade path for existing applications.

## Upload session migration

> [!TIP] Added in 5.3
> Needed by every install, whether or not [resumable uploads](./resumable-uploads)
> are used. Until it runs, the `cleanup` command skips its upload pass with a
> warning.

Run `CreateFileStorageUploads` (`20261008000000`) in the migration set that owns
`file_storage`. For plugin-managed tables:

```bash
bin/cake migrations migrate -p FileStorage
```

The migration adds the `file_storage_uploads` table. It stays empty until an
application routes the upload endpoint.

## Blob registry migration

> [!TIP] Added in 5.2
> Needed by every install, whether or not [deduplication](./deduplication) is
> used. Nothing changes for stored files until a collection is opted in.

Run `CreateFileStorageBlobs` (`20261006000000`) in the migration set that owns
`file_storage`. For plugin-managed tables:

```bash
bin/cake migrations migrate -p FileStorage
```

The migration adds the `file_storage_blobs` table with a unique `(adapter, hash)`
index, the nullable `file_storage.blob_id` column with an index and an
`ON DELETE RESTRICT` foreign key, and an index on `file_storage.hash`.
The table exists even when deduplication is disabled. Existing rows remain
private; this migration does not merge files. Enable sharing only after meeting
the [deduplication requirements](./deduplication).

## Database identity change

Older releases used `file_storage.id` as a `CHAR(36)` UUID primary key. Version 5.0 separates the two concerns:

| Column | Meaning |
|--------|---------|
| `id` | Integer database primary key for internal row references. |
| `uuid` | Public and storage identity used for signed URLs, file objects, and external references. |

Fresh installs run the rewritten initial migration and get both columns directly.

### Preserve the storage identity when you backfill

Storage paths are derived from the identity string that used to be `id` and is
now `uuid` (`{randomPath}` and `{strippedId}` are both computed from it). So
however you upgrade, **copy each legacy UUID `id` into `uuid`**: this keeps
every already-stored file resolvable and keeps `CleanupService` from treating
existing files as orphans.

::: danger Backfill from the existing id, never from random UUIDs
Populating `uuid` with fresh random values (e.g. `Text::uuid()`) for existing
rows repoints path generation away from the files already on disk. Variant
regeneration then writes to new locations and the cleanup command can delete
the old files. Preserve the original UUID identity.
:::

### Use the migration set that owns your table

Check `bin/cake migrations status -p FileStorage`. If the initial migration is
already `up`, apply the plugin's migration set. If your application created the
table itself, adapt the conversion, blob and upload migrations in your app's
migration set and run `bin/cake migrations migrate`. Do not run the plugin's
initial migration over an app-managed table. Preserve any custom columns and
update external foreign keys in your application migration.

### Existing UUID primary keys

Use the shipped full conversion below, or write an application migration that
produces the same integer-`id`, unique-`uuid` schema while preserving old UUIDs.
Adding a `uuid` column while keeping a legacy `CHAR(36)` primary key is not a
complete upgrade: new saves leave `id` to the database, and the blob migration
expects integer file-row references. The shipped conversion skips tables that
already have a `uuid` column, so do not add that column as a preliminary step.

If your app already converted its ids to integers, backfill `uuid` from the
preserved original storage identities. Copying new integers or generating random
UUIDs cannot recover those identities. Rehearse a custom migration against your
actual schema and file paths.

### Recovering from an earlier add-uuid-only upgrade

If you followed the previous add-uuid-only recipe and still have a UUID `id`,
do not rerun the shipped conversion unchanged: it skips tables with `uuid`.
Use an application migration to rebuild the integer primary key, preserve the
existing `uuid` values, and update application references. Rehearse it on a copy
of the database and keep a backup. Do not drop `uuid` blindly; existing rows or
external references may already rely on it.

### Expect four `** MISSING **` rows in the migration status

Version 5.0 folds the intermediate 4.x migrations into the rewritten initial
migration. The plugin now ships four migration files: the initial schema,
identity conversion, blobs and upload sessions. Existing installations still have the four collapsed ids recorded in their
`cake_migrations` table, and those rows now point at files that no longer exist:

```text
| up   | 20141213004653  | InitialMigration                                |
| up   | 20160302083933  | FixingMimeTypeField** MISSING **                |
| up   | 20170222133412  | UpdatingLengthOfFileExtensionField** MISSING ** |
| up   | 20200714095531  | AddingVariantsAndMetadataFields** MISSING **    |
| up   | 20201110234846  | AddCollectionColumn** MISSING **                |
| down | 20260703000000  | MigrateFileStorageToIntegerPrimaryKey           |
```

This is expected and harmless. The schema those four migrations produced is part
of the rewritten initial migration, they are already applied on your database,
and `bin/cake migrations migrate -p FileStorage` still runs. Only the status
output is affected.

Read it as follows when deciding which migration set owns your table: the
`InitialMigration` row is the one that answers the question above. If it shows
`up`, the plugin manages your table; the four `MISSING` rows below it do not
change that. Keep the migration history intact. The missing entries are historical records,
not pending migrations.

### Full restructure to an integer primary key

Destructive and intentionally irreversible. Use only if your table still has the
legacy `CHAR(36)` UUID primary key and you want the canonical integer-PK schema.
The shipped `MigrateFileStorageToIntegerPrimaryKey` migration:

- renames the old `file_storage` table to `file_storage_uuid_id_backup`;
- creates a new `file_storage` table with integer `id` and unique `uuid`;
- copies each old UUID primary key into the new `uuid` column;
- lets the database assign new integer `id` values.

```bash
bin/cake migrations migrate -p FileStorage
```

The migration is intentionally not reversible. If you need to roll back, restore
from `file_storage_uuid_id_backup` or from your database backup.

### Rehearse before production

Rehearse on a copy of your database first:

1. Apply the migration and confirm `uuid` is populated (`NOT NULL`, unique).
2. Load a few pages that render stored images: they must still resolve.
3. Regenerate variants for one model and confirm the files land at their
   existing paths (overwrite in place), not in a new parallel tree.
4. Run the cleanup command in dry-run mode and confirm it flags **zero**
   existing files as orphaned.

## App-side references

If your application stores references to the old `file_storage.id`, decide what
that reference means before migrating:

- use the new integer `file_storage.id` for internal database relations;
- use `file_storage.uuid` for public identifiers, signed links, storage-facing
  references, imported data, or references that must remain stable across the
  migration.

The plugin now exposes this split explicitly:

```php
$fileStorage->id; // integer row id
$fileStorage->uuid; // public/storage UUID
$fileStorage->publicId(); // same UUID, for public/storage-facing use
$fileStorageTable->getByUuid($uuid);
```

## Signed URLs

Signed URLs use the public/storage UUID:

```php
$url = \FileStorage\Utility\SignedUrlGenerator::url($fileStorage);
```

The route path is:

```text
/file-storage/signed/{uuid}/{signature}
```

If you build URLs manually, stop passing the integer row id. Use the UUID or the
generator.

## Image variant config (breaking: re-key by model)

The previous `FileStorage.useEntityModelForVariants` opt-in is now the default
behavior. `imageVariants` is resolved by the **persisted `file_storage.model`
value**, then the collection: `FileStorageBehavior::processImages()` reads
`$imageVariants[$model][$collection]`. Previously it resolved by the **FileStorage
association alias**.

::: danger This is a silent breaking change: re-key your config
If your `imageVariants` config is keyed by association alias and the alias
differs from the owning table alias, the lookup now misses. There is **no error**:
`processImages()` simply returns the file unprocessed, so new uploads are saved
with **empty `variants`** and thumbnails never appear. Re-key the outer level to
the model (the owning table alias) stored in `file_storage.model`.
:::

The outer key is the owning table's alias (what the `FileAssociation` behavior
writes into `file_storage.model`), not the association name:

```php
// Before: keyed by association alias (e.g. hasOne('ProfilePhotos'))
'FileStorage' => [
    'imageVariants' => [
        'ProfilePhotos' => [       // association alias
            'Photos' => $variants->toArray(),
        ],
    ],
],

// After: keyed by the persisted file_storage.model value
'FileStorage' => [
    'imageVariants' => [
        'Profiles' => [            // owning model / table alias
            'Photos' => $variants->toArray(),
        ],
    ],
],
```

If you are unsure what value your rows carry, check it:

```sql
SELECT DISTINCT model, collection FROM file_storage;
```

Then remove `useEntityModelForVariants` from your app config.

Any images uploaded **after** the upgrade but **before** you re-key are stored
with empty variants. After fixing the config, regenerate them:

```bash
bin/cake file_storage generate_image_variant <Model> <Collection>
```

Existing rows whose variants were generated before the upgrade are unaffected,
their variant paths are already persisted and still render; only the write path
(new uploads and regeneration) depends on this config.

## Removed compatibility virtual

The deprecated `variant_urls` virtual field has been removed. Use the explicit
methods instead:

```php
$fileStorage->getVariantUrl('thumbnail');
$fileStorage->getVariantPath('thumbnail');
```

## ImageHelper parameter renamed to `$variant`

The `ImageHelper` methods `display()`, `picture()` and `fallbackImage()` now name
their second parameter `$variant` (previously `$version`), matching `imageUrl()`
and the rest of the plugin's variant terminology. Positional calls are
unaffected. Only callers using PHP named arguments need to update:

```php
// Before
$this->Image->display($image, version: 'thumbnail');
// After
$this->Image->display($image, variant: 'thumbnail');
```

The removed `FileStorage::storageIdentity()` alias is another such rename: use
`FileStorage::publicId()`, which returns the same UUID.

## Moving files between adapters

When upgrading is also a good time to move files between configured adapters, for
example from `Local` to `S3`. Use the adapter migration command:

```bash
# Preview first
bin/cake file_storage migrate_adapter Local S3 --dryRun

# Then copy files and update rows
bin/cake file_storage migrate_adapter Local S3

# Remove source files after each row was copied and updated
bin/cake file_storage migrate_adapter Local S3 --deleteSource
```

The command copies the main file and all variant paths, then updates
`file_storage.adapter` only after every file for that row was copied
successfully. Missing source files skip that row. Existing target files skip that
row unless you pass `--overwrite`.

## CakePHP 6

This development branch uses CakePHP 6 and PHP 8.5 or newer. Application extensions use the inherited `defaultConfig`, `config`, `table` and `View` properties. Entity mass-assignment rules use `patchable`, and per-operation overrides use `patchableFields`. Command implementations use `execute()` with `$this->args` and `$this->io`.

CakePHP 6 no longer forwards arbitrary table methods to behaviors. Call custom behavior methods through `$table->getBehavior('FileStorage')`. Register listeners with `on($eventName, $listener, $options)` and plugin routes with `plugin($name, $callback, $options)`. Replace `disableHydration()` with `enableHydration(false)`.

For trusted HTML link titles, replace `escapeTitle => false` with `escape => false` and keep `escapeAttributes => true`. Ordinary text titles use the helper's default escaping. Form helpers accept raw URL strings and escape the form action themselves. For `HtmlHelper::link()` with `escape => false`, escape a configured string URL with `$this->Url->build($url, ['escape' => true])` before passing it to the helper.

The migration dependency currently uses the official `fix/6.x-cakephp-compatibility` branch while [Migrations #1121](https://github.com/cakephp/migrations/pull/1121) is pending. Follow [Installation](./installation) for the development dependency settings.
