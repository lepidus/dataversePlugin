<?php

import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');
import('plugins.generic.dataverse.classes.factories.DataverseServerFactory');

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
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $locale = AppLocale::getLocale();

        $dvServerFactory = new DataverseServerFactory();
        $dvServer = $dvServerFactory->createDataverseServer($contextId);

        $dvAPIClient = new NativeAPIClient($dvServer);
        $dvDataService = new DataAPIService($dvAPIClient);

        $dvCollectionName = $dvDataService->getDataverseCollectionName();

        $credentials = $dvServer->getCredentials();
        $termsOfUse = $credentials->getLocalizedData('termsOfUse', $locale);

        return [
            'termsOfUseURL' => $termsOfUse,
            'dataverseName' => $dvCollectionName
        ];
    }
}
