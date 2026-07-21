<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\{
    FormComponent, FieldOptions, FieldHTML, FieldRichTextarea
};
use PKP\facades\Locale;

class DeleteDatasetForm extends FormComponent
{
    public function __construct($action, $context, $defaultEmailBody)
    {
        $this->id = 'deleteDataset';
        $this->action = $action;
        $this->method = 'DELETE';
        $this->locales = $this->getFormLocales($context);
    
        $this->addField(new FieldHTML('confirmation', [
            'description' => __('plugins.generic.dataverse.modal.confirmDatasetDelete'),
            'groupId' => 'default',
        ]))
        ->addField(new FieldOptions('sendDeleteEmail', [
            'label' => __('common.sendEmail'),
            'type' => 'radio',
            'options' => [
                ['value' => 1, 'label' => __('plugins.generic.dataverse.researchData.delete.sendEmail.yes')],
                ['value' => 0, 'label' => __('plugins.generic.dataverse.researchData.delete.sendEmail.no')],
            ],
            'value' => 1,
            'groupId' => 'default'
        ]))
        ->addField(new FieldRichTextarea('deleteMessage', [
            'label' => __('plugins.generic.dataverse.researchData.delete.emailNotification'),
            'value' => $defaultEmailBody,
            'showWhen' => ['sendDeleteEmail', 1],
            'groupId' => 'default'
        ]));
    }

    private function getFormLocales($context): array
    {
        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = array_map(fn ($localeMetadata) => $localeMetadata->getDisplayName(), Locale::getLocales());

        $formLocales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        return $formLocales;
    }
}