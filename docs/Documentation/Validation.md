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
            'provider' => 'upload'
        ]);
        $validator->add('file', 'fileAboveMinHeight', [
            'rule' => ['isAboveMinHeight', 50],
            'message' => 'This image should at least be 50px high',
            'provider' => 'upload'
        ]);
        $validator->add('file', 'fileAboveMinWidth', [
            'rule' => ['isAboveMinWidth', 50],
            'message' => 'This image should at least be 50px wide',
            'provider' => 'upload'
        ]);

        $validator->add('file', 'customName', [
            'rule' => 'nameOfTheRule',
            'message' => 'yourErrorMessage',
            'provider' => 'upload',
            'on' => function($context) {
                return !empty($context['data']['file']);
            }
        ]);
    }

    /**
     * @param mixed $data
     *
     * @return false
     */
    public static function isUnderPhpSizeLimit($data): bool
    {
        ...
    }
}
