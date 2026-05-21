# The Variant Command

The `generate_image_variant` command generates, regenerates, and manages image
variants for stored files.

## Overview

```bash
bin/cake file_storage generate_image_variant [model] [collection] [variant]
```

## Arguments

All arguments are optional. If omitted, the command processes all configured
variants.

- **model** *(optional)* — model name of the images to process (e.g. `Posts`, `Users`).
- **collection** *(optional)* — collection name within the model (e.g. `Avatar`, `Cover`, `Gallery`).
- **variant** *(optional)* — specific variant to generate (e.g. `thumb`, `medium`, `large`).

## Options

| Option | Default | Description |
|--------|---------|-------------|
| `--storage`, `-s` | `FileStorage.FileStorage` | The storage table to use. |
| `--limit`, `-l` | `10` | Limit the number of records processed in one batch. |
| `--adapter`, `-a` | `Local` | The adapter config name to use. |
| `--force`, `-f` | — | Force regeneration even if variants already exist. Without it, existing variants are merged (preserved); with it, all variants are replaced. |
| `--dryRun`, `-d` | — | Preview what would be processed without making changes. |
| `--queue` | — | Enqueue one job per entity instead of processing inline. Requires `dereuromark/cakephp-queue`. See [below](#background-regeneration-via-cakephp-queue). |

## Examples

Generate all variants for all models/collections:

```bash
bin/cake file_storage generate_image_variant
```

Generate all variants for a specific model and collection:

```bash
bin/cake file_storage generate_image_variant Posts Avatar
```

Generate a specific variant:

```bash
bin/cake file_storage generate_image_variant Posts Avatar thumb
```

Force regeneration (replace existing variants):

```bash
bin/cake file_storage generate_image_variant Posts Avatar --force
```

Dry-run to preview what would be processed:

```bash
bin/cake file_storage generate_image_variant Posts Avatar --dryRun
```

Process with a custom adapter:

```bash
bin/cake file_storage generate_image_variant Posts Cover --adapter=S3
```

Process larger batches:

```bash
bin/cake file_storage generate_image_variant --limit=100
```

## Configuration

Image variants must be configured in your application configuration (e.g.
`config/app.php` or `config/app_local.php`):

```php
'FileStorage' => [
    'imageVariants' => [
        'Posts' => [
            'Cover' => [
                'thumb' => ['width' => 150, 'height' => 150, 'mode' => 'crop'],
                'medium' => ['width' => 600, 'height' => 400],
                'large' => ['width' => 1200, 'height' => 800],
            ],
            'Gallery' => [
                'thumb' => ['width' => 200, 'height' => 200, 'mode' => 'crop'],
            ],
        ],
        'Users' => [
            'Avatar' => [
                'small' => ['width' => 50, 'height' => 50, 'mode' => 'crop'],
                'medium' => ['width' => 150, 'height' => 150, 'mode' => 'crop'],
            ],
        ],
    ],
],
```

## How it works

1. The command reads the `imageVariants` configuration.
2. It queries the FileStorage table for matching records (by model/collection).
3. For each image it converts the entity to a `File` object, processes the
   configured variants, and saves the updated entity with the variant info.

::: tip Notes
- Image processing requires an image processor to be configured (e.g.
  Intervention Image).
- The command only processes images with the extensions `jpg`, `jpeg`, `png`.
- Use `--force` to regenerate existing variants (useful after changing variant
  settings). Without `--force`, new variants are added while keeping existing
  ones.
- The command removes the FileStorage behavior during save to prevent infinite
  loops.
:::

## Background regeneration via cakephp-queue

Inline processing blocks the calling shell for the whole batch — fine for a
one-off CLI run, but unworkable from an admin UI button on a deployment with
thousands of attachments. When `dereuromark/cakephp-queue` is installed, pass
`--queue` to enqueue one `FileStorage.ImageVariant` job per entity:

```bash
bin/cake file_storage generate_image_variant Posts Avatar --queue
```

Each job lands on the queue immediately; queue workers pick them up and run the
same processor pipeline per entity. Benefits:

- the admin "regenerate variants" action can call into the command and return
  immediately;
- failed jobs auto-retry per the queue config;
- renderer CPU cost spreads across multiple workers naturally.

### Enqueuing directly from app code

If you don't want to go through the CLI, build the job payload yourself:

```php
$queuedJobsTable = $this->fetchTable('Queue.QueuedJobs');
$queuedJobsTable->createJob('FileStorage.ImageVariant', [
    'id' => $fileStorage->id,
    'operations' => [
        'thumbnail' => ['width' => 100],
        'medium'    => ['width' => 600],
    ],
    'merge' => true,                             // keep existing variants
    'storageTable' => 'FileStorage.FileStorage', // optional
]);
```

The task validates the payload (a missing `id` or `operations` throws
`Queue\Model\QueueException` so the worker can apply retry / dead-letter
handling) and soft-fails — no exception, no log noise — when the entity has been
deleted between enqueue and dispatch.
