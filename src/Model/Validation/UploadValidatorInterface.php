<?php

declare(strict_types = 1);

namespace FileStorage\Model\Validation;

use Cake\Validation\Validator;

interface UploadValidatorInterface
{
    /**
     * @param \Cake\Validation\Validator $validator
     *
     * @return void
     */
    public function configure(Validator $validator): void;
}
