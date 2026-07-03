# Behavior Options

The `FileStorage.FileStorage` behavior wires upload handling into the
`FileStorage.FileStorage` table's save/delete lifecycle. The plugin table
attaches it automatically.

Only attach this behavior directly when you are creating a custom file storage
table that saves upload entities itself:

```php
$this->addBehavior('FileStorage.FileStorage', Configure::read('FileStorage.behaviorConfig'));
```

For uploads saved through another app table, such as `Posts` with a
`CoverImages` association, attach `FileStorage.FileAssociation` to the app table
instead. See [Usage](/guide/usage#use-the-right-behavior-for-the-right-table).

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `fileStorage` | `FileStorage` | *required* | The FileStorage instance used to store files. |
| `fileProcessor` | `ProcessorInterface\|null` | `null` | Image/file processor for generating variants. |
| `fileValidator` | `string\|UploadValidatorInterface\|null` | `null` | Validator class or instance for upload validation. |
| `fileField` | `string` | `'file'` | The form field name that contains the uploaded file. |
| `defaultStorageConfig` | `string` | `'Local'` | Default storage adapter name. |
| `ignoreEmptyFile` | `bool` | `true` | Skip processing when no file is uploaded. |
| `dataTransformer` | `DataTransformerInterface\|null` | `null` | Entity ↔ file-object transformer used by the image variant queue task. |

## Notes

### `fileField`

By default the behavior looks for a `'file'` field, so your form control should
be named `*.file`. To use a different name, set `fileField` and name your control
accordingly. See [Usage](/guide/usage#the-filefield-option).

### `fileProcessor`

Pass an `ImageProcessor` (or a `StackProcessor` combining several processors) to
generate image variants on upload. See
[Image variants and versioning](/images/#setting-up-the-image-processor).

### `fileValidator`

A class name or instance implementing
`FileStorage\Model\Validation\UploadValidatorInterface`. See
[Validation](/guide/validation).

### `dataTransformer`

Must be a `FileStorage\FileStorage\DataTransformerInterface` instance; anything
else is ignored. When unset, a `DataTransformer` bound to the storage table is
used. This is consumed by the image variant queue task — see
[The variant command](/images/command#background-regeneration-via-cakephp-queue).
