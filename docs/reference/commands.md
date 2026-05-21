# Console Commands

The plugin ships two console commands, both under the `file_storage` namespace.

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
