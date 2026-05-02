# Validation

In your config, you can set a `fileValidator` to be used:
```php
'FileStorage' => [
        'behaviorConfig' => [
            'fileValidator' => \App\FileStorage\Validator\ImageValidator::class,
        ],
    ],
```

Such a class would implement `FileStorage\Model\Validation\UploadValidatorInterface`:
```php
namespace App\FileStorage\Validator;

use Cake\Validation\Validator;
use FileStorage\Model\Validation\ImageValidationTrait;
use FileStorage\Model\Validation\UploadValidationTrait;
use FileStorage\Model\Validation\UploadValidatorInterface;

class ImageValidator implements UploadValidatorInterface
{
    use UploadValidationTrait;
    use ImageValidationTrait;

    public function configure(Validator $validator): void
    {
        $validator->setProvider('upload', static::class);

        $validator->add('file', 'fileUnderPhpSizeLimit', [
            'rule' => 'isUnderPhpSizeLimit',
            'message' => 'This file is too large',
            'provider' => 'upload',
        ]);

        // Validate that the file is actually an image before checking dimensions
        $validator->add('file', 'fileIsValidImage', [
            'rule' => 'isValidImage',
            'message' => 'File must be a valid image (JPEG, PNG, GIF, or WebP)',
            'provider' => 'upload',
            'on' => function($context) {
                $file = $context['data']['file'] ?? null;
                return $file && $file->getError() === UPLOAD_ERR_OK;
            },
        ]);

        $validator->add('file', 'fileAboveMinHeight', [
            'rule' => ['isAboveMinHeight', 50],
            'message' => 'This image should at least be 50px high',
            'provider' => 'upload',
            'on' => function($context) {
                $file = $context['data']['file'] ?? null;
                return $file && $file->getError() === UPLOAD_ERR_OK;
            },
        ]);
        $validator->add('file', 'fileAboveMinWidth', [
            'rule' => ['isAboveMinWidth', 50],
            'message' => 'This image should at least be 50px wide',
            'provider' => 'upload',
            'on' => function($context) {
                $file = $context['data']['file'] ?? null;
                return $file && $file->getError() === UPLOAD_ERR_OK;
            },
        ]);
    }
}
```

## Available Upload Validation Rules

The `UploadValidationTrait` provides the following validation methods. They all accept either a PSR-7 `UploadedFileInterface` or the legacy `$_FILES`-style array (`['tmp_name' => …, 'name' => …, 'type' => …, 'size' => …, 'error' => …]`).

### File-error checks

| Rule                        | Purpose                                                                                |
|-----------------------------|----------------------------------------------------------------------------------------|
| `isUnderPhpSizeLimit`       | False when the upload hit PHP's `upload_max_filesize` (`UPLOAD_ERR_INI_SIZE`).         |
| `isUnderFormSizeLimit`      | False when the upload hit the HTML form's `MAX_FILE_SIZE` (`UPLOAD_ERR_FORM_SIZE`).    |
| `isCompletedUpload`         | False when the upload was truncated (`UPLOAD_ERR_PARTIAL`).                            |
| `isFileUpload`              | False when no file was supplied (`UPLOAD_ERR_NO_FILE`).                                |
| `isSuccessfulWrite`         | False when the temp file could not be written (`UPLOAD_ERR_CANT_WRITE`).               |

### Size checks

| Rule                         | Purpose                              |
|------------------------------|--------------------------------------|
| `isAboveMinSize($check, $size)` | True when uploaded size ≥ `$size` bytes. |
| `isBelowMaxSize($check, $size)` | True when uploaded size ≤ `$size` bytes. |

### Allow-list checks (recommended for any upload that is not strictly an image)

The client-supplied `name` / `type` (`Content-Type`) headers are **not trustworthy** — a request can claim `image/jpeg` while delivering a `.php` shell. The MIME helper sniffs server-side via `finfo` against the actual file contents by default, so callers get the value PHP detects, not what the user told it to.

```php
// Restrict by extension (case-insensitive, leading dot tolerant).
$validator->add('file', 'fileExtensionAllowed', [
    'rule' => ['hasAllowedExtension', ['jpg', 'jpeg', 'png', 'webp']],
    'message' => 'Only JPEG, PNG, or WebP images are allowed.',
    'provider' => 'upload',
]);

// Restrict by sniffed MIME type (DEFAULT). Sniffs the actual file contents.
$validator->add('file', 'fileMimeAllowed', [
    'rule' => ['hasAllowedMimeType', ['image/jpeg', 'image/png', 'image/webp']],
    'message' => 'File contents must be a JPEG, PNG, or WebP image.',
    'provider' => 'upload',
]);

// Same, but trust the client header instead of sniffing. Only safe inside
// trusted code paths (tests, internal-only forms). Disable sniff with a `false`
// third argument:
$validator->add('file', 'fileMimeAllowedClient', [
    'rule' => ['hasAllowedMimeType', ['image/jpeg'], false],
    'message' => '…',
    'provider' => 'upload',
]);
```

For images, `ImageValidationTrait::isValidImage()` (below) is a stricter check — it actually parses the image header — and should be preferred over `hasAllowedMimeType` when you only accept images.

## Available Image Validation Rules

The `ImageValidationTrait` provides the following validation methods:

### isValidImage

Validates that the uploaded file is a valid image. Use this before dimension checks to provide clear error messages when non-image files are uploaded.

```php
// Default: allows JPEG, PNG, GIF, WebP
$validator->add('file', 'fileIsValidImage', [
    'rule' => 'isValidImage',
    'message' => 'File must be a valid image',
    'provider' => 'upload',
]);

// Custom allowed types
$validator->add('file', 'fileIsValidImage', [
    'rule' => ['isValidImage', [IMAGETYPE_JPEG, IMAGETYPE_PNG]],
    'message' => 'File must be a JPEG or PNG image',
    'provider' => 'upload',
]);
```

### Dimension Validators

- `isAboveMinWidth($check, int $width)` - Check minimum width
- `isBelowMaxWidth($check, int $width)` - Check maximum width
- `isAboveMinHeight($check, int $height)` - Check minimum height
- `isBelowMaxHeight($check, int $height)` - Check maximum height

All dimension validators safely handle non-image files by returning `false` instead of throwing an error.
