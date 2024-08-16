<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FieldOptions;

class DraftDatasetFileForm extends FormComponent
{
    public function __construct($action, $context)
    {
        $this->action = $action;
        $this->id = 'datasetFileForm';
        $this->method = 'POST';

        $termsOfUseParams = $this->getTermsOfUseData($context->getId());
        $temporaryFileApiUrl = $this->getTemporaryFileApiUrl($context);

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

    private function getTemporaryFileApiUrl($context): string
    {
        $request = Application::get()->getRequest();
        $temporaryFileApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'temporaryFiles');
        return $temporaryFileApiUrl;
    }

    private function getTermsOfUseData($contextId)
    {
        $locale = AppLocale::getLocale();

        import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
        $dataverseClient = new DataverseClient();

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);

        try {
            $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
            $termsOfUse = $configuration->getLocalizedData('termsOfUse', $locale);
            return [
                'dataverseName' => $dataverseCollection->getName(),
                'termsOfUseURL' => $termsOfUse
            ];
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
            return [];
        }
    }
}
