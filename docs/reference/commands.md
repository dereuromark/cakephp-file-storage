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

### What it reports

The command delegates to `FileStorage\Service\CleanupService` and reports:

- the number of rows checked;
- **orphan rows** — rows whose owning record no longer exists (deleted, or would
  be deleted with `--dryRun`);
- **orphan files** — files on disk with no matching row (deleted, or would be);
- **missing files** — rows whose backing file has disappeared from the adapter;
- any additional warnings.

The same logic backs the admin
[Cleanup UI](/admin/#cleanup). Use the CLI for cron-driven runs:

```bash
# Preview first
bin/cake file_storage cleanup --dryRun

# Then run for real, scoped to one model/collection
bin/cake file_storage cleanup Posts Cover
```

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
