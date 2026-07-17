# Upgrading

This page covers the next major upgrade path for existing applications.

## Database identity change

Older releases used `file_storage.id` as a `CHAR(36)` UUID primary key. The next
major release uses the normal CakePHP convention:

| Column | Meaning |
|--------|---------|
| `id` | Integer database primary key for internal row references. |
| `uuid` | Public and storage identity used for signed URLs, file objects, and external references. |

Fresh installs run the rewritten initial migration and get the final schema
directly. Existing installs already have the old initial migration recorded, so
they receive the dedicated upgrade migration instead.

The upgrade migration:

- renames the old `file_storage` table to `file_storage_uuid_id_backup`;
- creates a new `file_storage` table with integer `id` and unique `uuid`;
- copies each old UUID primary key into the new `uuid` column;
- lets the database assign new integer `id` values.

Run the migration as usual:

```bash
bin/cake migrations migrate -p FileStorage
```

The migration is intentionally not reversible. If you need to roll back, restore
from `file_storage_uuid_id_backup` or from your database backup.

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

## Image variant config

The previous `FileStorage.useEntityModelForVariants` opt-in is now the default
behavior. Variant config is resolved by the persisted `file_storage.model` value:

```php
'FileStorage' => [
    'imageVariants' => [
        'Posts' => [
            'Cover' => $coverVariants->toArray(),
        ],
    ],
],
```

Remove `useEntityModelForVariants` from app config during the upgrade. If your
old config was keyed by FileStorage association aliases, re-key it by the owning
model stored in `file_storage.model`.

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
