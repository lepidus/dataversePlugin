<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use APP\core\Application;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FormComponent;
use PKP\db\DAORegistry;
use PKP\facades\Locale;

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
            'label' => __('plugins.generic.dataverse.modal.addFile.datasetFileLabel'),
            'isRequired' => true,
            'value' => null,
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
        $temporaryFileApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'temporaryFiles');
        return $temporaryFileApiUrl;
    }

    private function getTermsOfUseData($contextId)
    {
        $locale = Locale::getLocale();
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $termsOfUse = $configuration->getLocalizedData('termsOfUse', $locale);

        return ['termsOfUserUrl' => $termsOfUse];
    }
}
