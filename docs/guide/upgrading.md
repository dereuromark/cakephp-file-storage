# Upgrading

This page covers the next major upgrade path for existing applications.

## Database identity change

Older releases used `file_storage.id` as a `CHAR(36)` UUID primary key. The next
major release separates the two concerns:

| Column | Meaning |
|--------|---------|
| `id` | Integer database primary key for internal row references. |
| `uuid` | Public and storage identity used for signed URLs, file objects, and external references. |

Fresh installs run the rewritten initial migration and get both columns directly.

### Preserve the storage identity when you backfill

Storage paths are derived from the identity string that used to be `id` and is
now `uuid` (`{randomPath}` and `{strippedId}` are both computed from it). So
however you upgrade, **backfill `uuid` from the existing `id`** — this keeps
every already-stored file resolvable and keeps `CleanupService` from treating
existing files as orphans.

::: danger Backfill from the existing id, never from random UUIDs
Populating `uuid` with fresh random values (e.g. `Text::uuid()`) for existing
rows repoints path generation away from the files already on disk. Variant
regeneration then writes to new locations and the cleanup command can delete
the old files. Always backfill `uuid` from the current `id`.
:::

Existing installs choose **one** of the two paths below. Do not run both.

### Path A — Minimal: add `uuid`, keep your current primary key (recommended)

Non-destructive and reversible. Adds a `uuid` column and backfills it from the
existing `id`, leaving your primary key as-is. Works whether your `id` is the
legacy `CHAR(36)` UUID or you have already switched it to an integer. This is the
right path if you customized the `file_storage` schema, or manage the table with
your own application migrations.

Add this migration to your application's `config/Migrations/` (adjust the
timestamp prefix in the file name) and run it:

```php
<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddUuidToFileStorage extends BaseMigration
{
    public function up(): void
    {
        // 1. Add nullable uuid so it can be backfilled before enforcing NOT NULL/UNIQUE.
        $this->table('file_storage')
            ->addColumn('uuid', 'char', [
                'limit' => 36,
                'null' => true,
                'default' => null,
                'after' => 'id',
            ])
            ->update();

        // 2. Identity-preserving backfill: copy the existing id into uuid so the new
        //    code recomputes the same storage paths for already-stored files.
        $this->execute('UPDATE file_storage SET uuid = CAST(id AS CHAR) WHERE uuid IS NULL');

        // 3. Enforce NOT NULL + UNIQUE now every row has a value.
        $this->table('file_storage')
            ->changeColumn('uuid', 'char', ['limit' => 36, 'null' => false])
            ->addIndex(['uuid'], ['unique' => true, 'name' => 'file_storage_uuid_unique'])
            ->update();
    }

    public function down(): void
    {
        $this->table('file_storage')
            ->removeIndexByName('file_storage_uuid_unique')
            ->removeColumn('uuid')
            ->update();
    }
}
```

```bash
bin/cake migrations migrate
```

::: tip Which migration set?
If the plugin manages your `file_storage` table (the plugin migrations show as
`up` in `bin/cake migrations status -p FileStorage`), you can instead place a
copy of this migration in the plugin's migration set and run
`bin/cake migrations migrate -p FileStorage`. If your application already owns
the table (the plugin migrations show as `down`), keep the migration in your
app and run `bin/cake migrations migrate` — do **not** run `-p FileStorage`, or
it will try to apply the plugin's initial migration on top of your table.
:::

### Path B — Full restructure to an integer primary key

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

Whichever path you choose, rehearse on a copy of your database first:

1. Apply the migration and confirm `uuid` is populated (`NOT NULL`, unique).
2. Load a few pages that render stored images — they must still resolve.
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
value**, then the collection — `FileStorageBehavior::processImages()` reads
`$imageVariants[$model][$collection]`. Previously it resolved by the **FileStorage
association alias**.

::: danger This is a silent breaking change — re-key your config
If your `imageVariants` config is keyed by association alias and the alias
differs from the owning table alias, the lookup now misses. There is **no error**:
`processImages()` simply returns the file unprocessed, so new uploads are saved
with **empty `variants`** and thumbnails never appear. Re-key the outer level to
the model (the owning table alias) stored in `file_storage.model`.
:::

The outer key is the owning table's alias (what the `FileAssociation` behavior
writes into `file_storage.model`), not the association name:

```php
// Before — keyed by association alias (e.g. hasOne('ProfilePhotos'))
'FileStorage' => [
    'imageVariants' => [
        'ProfilePhotos' => [       // association alias
            'Photos' => $variants->toArray(),
        ],
    ],
],

// After — keyed by the persisted file_storage.model value
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

Existing rows whose variants were generated before the upgrade are unaffected —
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
