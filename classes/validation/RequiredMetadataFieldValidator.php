<?php

namespace APP\plugins\generic\dataverse\classes\validation;

use PKP\validation\ValidatorFactory;

class RequiredMetadataFieldValidator
{
    public function validate(string $type, $value): array
    {
        $validationRules = $this->getValidationRules($type);

        if (empty($validationRules)) {
            return [];
        }

        $validator = ValidatorFactory::make(
            ['value' => $value],
            ['value' => $validationRules['rules']]
        );

        if ($validator->passes()) {
            return [];
        }

        return $validationRules['useValidatorMessages']
            ? $validator->errors()->getMessages()['value']
            : [__($validationRules['errorKey'])];
    }

    private function getValidationRules(string $type): array
    {
        $rules = [
            'DATE' => [
                'rules' => ['required', 'date', 'date_format:Y-m-d'],
                'useValidatorMessages' => true,
            ],
            'URL' => [
                'rules' => ['required', 'url'],
                'errorKey' => 'validator.url',
                'useValidatorMessages' => false,
            ],
            'EMAIL' => [
                'rules' => ['required', 'email_or_localhost'],
                'errorKey' => 'validator.email',
                'useValidatorMessages' => false,
            ],
        ];

        return $rules[$type] ?? [];
    }
}
