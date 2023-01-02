<?php

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

    private function getTermsOfUseData($contextId) {
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $locale = AppLocale::getLocale();

        $configuration = new DataverseConfiguration(
            $plugin->getSetting($contextId, 'dataverseUrl'),
            $plugin->getSetting($contextId, 'apiToken')
        );

        $serviceFactory = new DataverseServiceFactory();
        $service = $serviceFactory->build($configuration, $plugin);

        $termsOfUse = DAORegistry::getDAO('DataverseDAO')->getTermsOfUse($contextId, $locale);
        $dataverseName = $service->getDataverseName();

        return [
            'termsOfUseURL' => $termsOfUse,
            'dataverseName' => $dataverseName
        ];
    }
}
