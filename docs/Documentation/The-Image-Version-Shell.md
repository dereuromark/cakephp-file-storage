# Image Variant Generate Command

The command allows you to generate, regenerate, and manage image variants for stored files.

## Command Overview

```sh
bin/cake file_storage generate_image_variant [model] [collection] [variant]
```

## Arguments

All arguments are optional. If omitted, the command will process all configured variants.

* **model** (optional): Model name of the images to process (e.g., `Posts`, `Users`)
* **collection** (optional): Collection name within the model (e.g., `Avatar`, `Cover`, `Gallery`)
* **variant** (optional): Specific variant to generate (e.g., `thumb`, `medium`, `large`)

## Options

* **--storage, -s**: The storage table for image processing you want to use.
  * Default: `FileStorage.FileStorage`
* **--limit, -l**: Limits the amount of records to be processed in one batch.
  * Default: 10
* **--adapter, -a**: The adapter config name to use.
  * Default: `Local`
* **--force, -f**: Force regeneration of variants even if they already exist.
  * Without this flag, existing variants are merged (preserved)
  * With this flag, all variants are replaced
* **--dryRun, -d**: Dry-run only (preview what would be processed without making changes).

## Examples

### Generate all variants for all models/collections

```sh
bin/cake file_storage generate_image_variant
```

### Generate all variants for a specific model and collection

```sh
bin/cake file_storage generate_image_variant Posts Avatar
```

### Generate a specific variant

```sh
bin/cake file_storage generate_image_variant Posts Avatar thumb
```

### Force regeneration (replace existing variants)

```sh
bin/cake file_storage generate_image_variant Posts Avatar --force
```

### Dry-run to preview what would be processed

```sh
bin/cake file_storage generate_image_variant Posts Avatar --dryRun
```

### Process with custom adapter

```sh
bin/cake file_storage generate_image_variant Posts Cover --adapter=S3
```

### Process larger batches

```sh
bin/cake file_storage generate_image_variant --limit=100
```

## Configuration

Image variants must be configured in your application's configuration file (e.g., `config/app.php` or `config/app_local.php`):

```php
'FileStorage' => [
    'imageVariants' => [
        'Posts' => [
            'Cover' => [
                'thumb' => [
                    'width' => 150,
                    'height' => 150,
                    'mode' => 'crop',
                ],
                'medium' => [
                    'width' => 600,
                    'height' => 400,
                ],
                'large' => [
                    'width' => 1200,
                    'height' => 800,
                ],
            ],
            'Gallery' => [
                'thumb' => [
                    'width' => 200,
                    'height' => 200,
                    'mode' => 'crop',
                ],
            ],
        ],
        'Users' => [
            'Avatar' => [
                'small' => [
                    'width' => 50,
                    'height' => 50,
                    'mode' => 'crop',
                ],
                'medium' => [
                    'width' => 150,
                    'height' => 150,
                    'mode' => 'crop',
                ],
            ],
        ],
    ],
],
```

## How It Works

1. The command reads the `imageVariants` configuration
2. It queries the FileStorage table for matching records (by model/collection)
3. For each image:
   - Converts the entity to a File object
   - Processes configured variants
   - Saves the updated entity with variant information

## Notes

* Image processing requires an image processor to be configured (e.g., Intervention/Image)
* The command only processes images with extensions: `jpg`, `jpeg`, `png`
* Use `--force` to regenerate existing variants (useful after changing variant settings)
* Without `--force`, new variants are added while keeping existing ones
* The command removes the FileStorage behavior during save to prevent infinite loops

The new command requires both model and collection names to be specified.
