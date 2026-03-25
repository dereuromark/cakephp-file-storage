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
                return $file && $file->getError() == UPLOAD_ERR_OK;
            },
        ]);

        $validator->add('file', 'fileAboveMinHeight', [
            'rule' => ['isAboveMinHeight', 50],
            'message' => 'This image should at least be 50px high',
            'provider' => 'upload',
            'on' => function($context) {
                $file = $context['data']['file'] ?? null;
                return $file && $file->getError() == UPLOAD_ERR_OK;
            },
        ]);
        $validator->add('file', 'fileAboveMinWidth', [
            'rule' => ['isAboveMinWidth', 50],
            'message' => 'This image should at least be 50px wide',
            'provider' => 'upload',
            'on' => function($context) {
                $file = $context['data']['file'] ?? null;
                return $file && $file->getError() == UPLOAD_ERR_OK;
            },
        ]);
    }
}
```

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
