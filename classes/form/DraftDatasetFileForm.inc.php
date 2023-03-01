<?php

import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FieldOptions;

class DraftDatasetFileForm extends FormComponent
{
    public function __construct($action, $context, $locales, $temporaryFileApiUrl)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->id = 'datasetFileForm';
        $this->method = 'POST';

        $termsOfUseParams = $this->getTermsOfUseData($context->getId());

        $this->addField(new FieldUpload('datasetFile', [
            'isRequired' => true,
            'label' => __('plugins.generic.dataverse.modal.addFile.datasetFileLabel'),
            'options' => [
                'url' => $temporaryFileApiUrl,
            ]
        ]))
        ->addField(new FieldOptions('termsOfUse', [
            'isRequired' => true,
            'label' => __('plugins.generic.dataverse.termsOfUse.label'),
            'options' => [
                ['value' => true, 'label' => __('plugins.generic.dataverse.termsOfUse.description', $termsOfUseParams)],
            ],
            'value' => false
        ]));
    }

    private function getTermsOfUseData($contextId)
    {
        $locale = AppLocale::getLocale();

        $client = new NativeAPIClient($contextId);
        $service = new DataAPIService($client);

        $dvCollectionName = $service->getDataverseCollectionName();
        $termsOfUse = $client->getCredentials()->getLocalizedData('termsOfUse', $locale);

        return [
            'dataverseName' => $dvCollectionName,
            'termsOfUseURL' => $termsOfUse
        ];
    }
}
