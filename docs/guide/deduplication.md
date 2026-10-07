# Deduplication

Deduplication stores identical uploads once per adapter. Each upload keeps its
own `file_storage` row, UUID, filename, owner, and variants. Replacing one row's
file claims a new blob without changing the files served by other rows.
Variants remain separate for each row.

## Requirements

Run the `CreateFileStorageBlobs` migration before enabling deduplication. It adds
`file_storage_blobs`, the nullable `file_storage.blob_id` foreign key and its
index, and an index on `file_storage.hash`. See [Upgrading](./upgrading).
Existing files are not backfilled or merged.

Use `php-collective/file-storage` 1.1+ and configure its path builder with a
`hashPathTemplate` under `blobs/`, for example:

```php
$pathBuilder = new PathBuilder([
    'hashPathTemplate' => 'blobs/{hash}.{extension}',
]);
```

The blob filename contains the SHA-256 hash and the first uploader's extension.
It does not contain the original filename.

## Configuration

```php
'FileStorage' => [
    'hashAlgorithm' => 'sha256',
    'deduplicate' => [
        'collections' => false,
        'gracePeriod' => 3600,
        'root' => 'blobs',
    ],
],
```

| `collections` value | Effect |
| --- | --- |
| `false` | Off, the default. |
| `true` | Every model and collection. |
| `[]` | Off. |
| `['Documents' => true]` | Every collection of model `Documents`, including rows with no collection. |
| `['Documents' => ['Attachments' => true]]` | Only the `Attachments` collection of `Documents`. |

Keys match the persisted `file_storage.model` and `file_storage.collection`
values. A row with no collection matches only the model-level `true` form.
`root` must match the static prefix of `hashPathTemplate`. Deduplication
requires `hashAlgorithm` to be `sha256`.

### `gracePeriod`

How long, in seconds, a stored file is kept after nothing uses it any more.
The default is `3600`, one hour.

Deleting a row never deletes its blob. The blob stays until
[cleanup](#scheduled-cleanup) runs, and cleanup only removes a blob when both
are true: no row references it, and its last use by an upload is at least
`gracePeriod` seconds ago. "Last use" is the moment an upload stored or reused
that content.

The delay exists for two reasons:

- **Uploads in progress.** A first upload writes the file before its database
  transaction commits. For that moment the file exists and no committed row
  points at it. On MySQL and PostgreSQL a row lock keeps cleanup away from it,
  and the grace period is a second safety margin. SQLite has no row locks, so
  there the grace period carries most of that protection and must be longer
  than your slowest upload including variant processing.
- **Content that comes back.** A file that is deleted and uploaded again
  shortly after, or replaced back and forth, reuses the stored blob instead of
  being deleted and written again.

A larger value keeps unused files on storage longer. A smaller value frees
storage sooner. Do not set it below the duration of your slowest upload.

## Existing files

Enabling deduplication changes how new uploads are stored. Files that are
already stored stay exactly where they are.

- Rows uploaded before keep their path and have no `blob_id`. They are served,
  replaced and deleted as before.
- They are not matched against new uploads. Uploading a copy of an old file
  stores that content once more, as a blob, and only later uploads of the same
  bytes share it.
- Rows from before 5.1 also have no `hash`, because the plugin did not write
  one then.
- Replacing the file on an old row in an opted-in collection moves that row to
  a blob. Its previous file is not removed by that save.

There is no command yet that hashes existing rows or merges existing
duplicates into blobs. Until there is, deduplication saves storage for content
uploaded after you switch it on.

Switching it off again is safe. Rows that point at a blob keep working, new
uploads get their own file again, and cleanup still removes blobs once nothing
references them.

## Transactions and databases

::: warning Atomic saves required
A deduplicated save must be atomic, with the file table's connection inside a
transaction. Unsupported drivers, a missing hash, or another hash algorithm
cause a `RuntimeException` before the row is written. The transformed file must
implement `ContentHashInterface` before storage is touched.
:::

| Database | Support |
| --- | --- |
| MySQL 5.7+ / MariaDB, PostgreSQL | Full support, including concurrent uploads and cleanup. |
| SQLite 3.24+ | Development and tests on one connection only. No row locks or foreign key enforcement protect concurrent writers. |
| Others | A deduplicated save throws. |

The blob lock stays held through variant processing. Another upload of the same
content waits and can reach the database lock timeout. Saves containing several
files claim blobs in input order. Concurrent saves claiming the same files in
opposite orders can deadlock; the database aborts one save and the caller must
retry it. No data is lost.

A parent table using another connection does not share the file transaction.
Saves from other callbacks while the behavior is detached for its metadata save
bypass blob claims.

## Scheduled cleanup

::: tip Schedule cleanup to free storage
Deleting a deduplicated row removes its variants but leaves the shared blob.
Run `bin/cake file_storage cleanup` on a schedule to reclaim storage.
:::

::: warning Rows without a `foreign_key`
A full cleanup run also deletes every `file_storage` row whose `foreign_key` is
empty. If your application keeps such rows on purpose, schedule
`bin/cake file_storage cleanup --blobsOnly` instead. It runs only the two blob
passes described below. In PHP the same is `CleanupService::runBlobs()`.
:::

Cleanup removes unreferenced blobs older than the grace period, deleting their
rows and files together. A failed file deletion keeps the blob row and reports
a warning. It also scans the blob root for old files with no matching blob row,
using a database lock before deleting them. Unknown modification times and
filenames without a 64-character hex hash produce warnings and are skipped.
That scan covers the default adapter and every adapter named in a file or blob
row. An adapter that only ever saw failed uploads is not scanned.

The ordinary orphan-file pass leaves the blob root alone. Both blob passes run
across all models and collections, even during a scoped cleanup and even when
`collections` is `false`. Preview with `--dryRun` to report candidates without
removing rows or files.

## Moving blobs between adapters

`bin/cake file_storage migrate_adapter Local S3` claims each row's content on
the target adapter. An existing target blob supplies its path, even when that
path differs from the source. Otherwise the command copies the main file once
and records the target blob. Rows sharing content share one target blob.

Variants use the normal target-exists and `--overwrite` rules. The command
updates `adapter`, `path`, and `blob_id` together in a transaction per row.
`--deleteSource` removes source variants only; source blobs and their rows stay
until cleanup can remove them. `--dryRun` makes no claims or writes.

## Limits

Deduplication does not skip the upload, merge existing duplicates, deduplicate
variants, or isolate blobs by tenant. Rows sharing a blob share its direct URL.
Duplicate uploads finish faster, which can reveal that content already exists.

Variant paths depend on the UUID, filename, and variant name. Replacing a file
under the same filename overwrites variants in place. If the save fails, the
row rolls back but those variants can show the replacement image. A processor
that fails partway through leaves the variants it already wrote. Rebuild these
derived files with `bin/cake file_storage generate_image_variant --force`.

Storage is reclaimed only when cleanup runs.
