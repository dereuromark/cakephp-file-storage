# Console Commands

The plugin ships console commands under the `file_storage` namespace.

## `file_storage cleanup`

Reconciles the `file_storage` table against the actual storage backend and
removes orphans.

```bash
bin/cake file_storage cleanup [model] [collection] [options]
```

### Arguments

- **model** *(optional)* — limit the scan to this model.
- **collection** *(optional)* — limit the scan to this collection.

### Options

| Option | Description |
|--------|-------------|
| `--dryRun`, `-d` | Preview only — report what would change without deleting anything. |
| `--blobsOnly`, `-b` | Only remove unreferenced [deduplicated](/guide/deduplication) blobs and stray blob files. Orphan rows and orphan files are left alone; `model` and `collection` are ignored. |

### What it reports

The command delegates to `FileStorage\Service\CleanupService` and reports:

- the number of rows checked;
- **orphan rows** — rows whose owning record no longer exists (deleted, or would
  be deleted with `--dryRun`);
- **orphan files** — files on disk with no matching row (deleted, or would be);
- **missing files** — rows whose backing file has disappeared from the adapter;
- **blobs**: unreferenced blob rows and their files older than the grace period;
- **stray blob files**: old files under the blob root without a matching blob row;
- **skipped blobs**: candidates kept because of references, locks, or unsafe file metadata;
- any additional warnings.

The blob passes always cover all models and collections, even when arguments
scope the ordinary passes or deduplication is disabled. The orphan-file pass
excludes the blob root. Files with unknown modification times or non-hash names
are skipped with warnings. Dry runs show the would-be deletion counts; for stray blob files that count is an upper bound, because a real run skips a file whose blob is locked by an upload at that moment. Schedule
cleanup to free storage left after deduplicated rows are deleted.

The same logic backs the admin
[Cleanup UI](/admin/#cleanup). Use the CLI for cron-driven runs:

```bash
# Preview first
bin/cake file_storage cleanup --dryRun

# Then run for real, scoped to one model/collection
bin/cake file_storage cleanup Posts Cover
```

## `file_storage deduplicate`

Converts existing rows in opted-in collections to shared blobs, keeping every
old source file. It never deletes old files or changes variants.

```bash
bin/cake file_storage deduplicate [model] [collection] [options]
```

### Arguments and options

`model` and `collection` optionally filter the persisted row values.

| Option | Description |
|--------|-------------|
| `--dryRun`, `-d` | Read metadata only. Report candidate rows, bytes, empty hashes, and estimated duplicates from stored hashes. |
| `--hashOnly` | Fill empty hashes without conversion, for any collection and any valid configured hash algorithm whose digest fits the column. |
| `--limit N` | Attempt at most N rows, including skips and failures. N must be positive. |
| `--manifest <file>` | Append row id, adapter, old path, new path, and hash to a local tab separated file for each committed conversion. Flush each line. Dry runs do not write it. |

### Conversion steps

1. Check storage configuration, SHA-256, a supported database, and the blob
   registry migration. Read the highest row id and use batches of 100,
   ordered by id. Rows above that id wait for the next run.
2. Skip collections that are not opted in and paths under the reserved blob root.
   Lock each candidate row and recheck its path, adapter, and blob reference.
3. Download the source to a local temporary copy while hashing its bytes.
   Check the adapter's size against bytes read; warn if the row's size differs.
4. Claim the hash on that adapter under the blob lock. Resolve its registered
   path, or build one using `hashPathTemplate`. Require a filename equal to the
   hash under the blob root, outside `.tmp/`.
5. Hash an existing destination before reuse. Different bytes fail the row
   without changing that file. For a missing destination, upload under
   `<root>/.tmp/`, read back and hash, move to the final path, and read back and
   hash again.
6. Register the blob path, update `path`, `blob_id`, and `hash` conditionally,
   read the row back, and commit. Keep `modified` and variants unchanged.
7. Append to the manifest if requested and dispatch `FileStorage.blobConverted`
   after commit. A listener failure is a warning, not a failed conversion.

`--hashOnly` only updates an empty hash while the row still has the same source
path and adapter. It writes nothing to storage. Conversion recomputes the hash
even when a stored value already exists. Row failures are reported and the run
continues. Preconditions and configuration errors return an error exit code;
ordinary row failures do not. Detail samples are capped at 50 per category,
with totals reported separately.

### Costs and URLs

Each candidate is downloaded once. A new blob is uploaded once and read back
twice, at its temporary and final paths. Existing destinations are read once
for verification. Database locks stay held during a row's storage operations.
There is no undo. SQLite requires exclusive database use during the run.

Serving-controller URLs keep working. Signed URLs issued before conversion
stop working for converted rows because their signature includes the old path.
Direct URLs to the old path work while the old file is kept.

::: warning Storage and reserved paths
Storage grows until old files are removed. This command never removes them.
Full `file_storage cleanup` removes unreferenced old files on the local adapter.
On other adapters there is no automatic removal yet; keep the manifest as the
record of old paths that later removal must work from.
No `pathTemplate` or `variantPathTemplate` may place files under the blob root.
The directory is reserved for blobs; legacy rows there are skipped.
:::

::: warning Cleanup during conversion
Do not run `file_storage cleanup` while conversion is running. Run cleanup
when traffic is low. A request that loaded a row just before conversion can
fail once if cleanup removes the old file immediately afterward. Preview full
cleanup first: it also deletes rows without a `foreign_key`.
:::

## `file_storage generate_image_variant`

Generates, regenerates, and manages image variants for stored files. This command
has its own page with all arguments, options, and the queue-backed background
mode:

- [The variant command](/images/command)

## `file_storage migrate_adapter`

Copies stored files from one configured adapter to another and updates matching
`file_storage.adapter` rows after each row's files were copied successfully.

```bash
bin/cake file_storage migrate_adapter <source> <target> [options]
```

### Arguments

- **source** — source adapter config name, e.g. `Local`.
- **target** — target adapter config name, e.g. `S3`.

### Options

| Option | Description |
|--------|-------------|
| `--dryRun`, `-d` | Preview only; do not copy files or update rows. |
| `--model` | Limit the migration to one `file_storage.model`. |
| `--collection` | Limit the migration to one collection. |
| `--limit` | Maximum number of rows to inspect. |
| `--overwrite` | Replace files that already exist on the target adapter. |
| `--deleteSource` | Delete source files after a successful copy and row update. |

Always run `--dryRun` first. Missing source files and existing target files skip
the affected row, unless `--overwrite` allows replacing target files.


For rows with `blob_id`, each row moves in one transaction. The command reuses
an existing target blob and its path, or copies the main file and records a new
blob. Target-exists and overwrite rules apply only to variants for these rows.
`--deleteSource` removes their source variants but leaves source blob files and
rows for cleanup. Dry runs make no claims or writes. See
[Deduplication](/guide/deduplication).

### Expired uploads

`bin/cake file_storage cleanup --uploadsOnly` removes expired resumable sessions and abandoned part files. Add `--dryRun` to preview. Busy locks are skipped; failed part removal keeps the session reservation. The full cleanup command includes this pass across all models and collections. Reports include `deletedUploads`, `deletedUploadParts`, and `skippedUploads`.
