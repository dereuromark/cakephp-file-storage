<?php

declare(strict_types = 1);

namespace TestApp\Storage\Validation;

use Cake\Validation\Validator;
use FileStorage\Model\Validation\ImageValidationTrait;
use FileStorage\Model\Validation\UploadValidationTrait;
use FileStorage\Model\Validation\UploadValidatorInterface;

class ImageValidator implements UploadValidatorInterface
{
    use UploadValidationTrait;
    use ImageValidationTrait;

    /**
     * @param \Cake\Validation\Validator $validator
     *
     * @return void
     */
    public function configure(Validator $validator): void
    {
        $validator->setProvider('upload', static::class);

        $validator->add('file', 'fileUnderPhpSizeLimit', [
            'rule' => 'isUnderPhpSizeLimit',
            'message' => 'This file is too large',
            'provider' => 'upload',
        ]);
        $validator->add('file', 'fileAboveMinHeight', [
            'rule' => ['isAboveMinHeight', 50],
            'message' => 'This image should at least be 50px high',
            'provider' => 'upload',
        ]);
        $validator->add('file', 'fileAboveMinWidth', [
            'rule' => ['isAboveMinWidth', 50],
            'message' => 'This image should at least be 50px wide',
            'provider' => 'upload',
        ]);
        $validator->add('file', 'fileBelowMaxWidth', [
            'rule' => ['isBelowMaxWidth', 400],
            'message' => 'This image should at max 400px wide',
            'provider' => 'upload',
        ]);

        $validator->add('file', 'customName', [
            'rule' => 'nameOfTheRule',
            'message' => 'yourErrorMessage',
            'provider' => 'upload',
            'on' => function ($context) {
                return !empty($context['data']['file']);
            },
        ]);
    }

    /**
     * @param $data
     *
     * @return false
     */
    public static function nameOfTheRule($data): bool
    {
        return false;
    }
}
