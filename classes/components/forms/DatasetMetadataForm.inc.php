<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldControlledVocab;
use PKP\components\forms\FieldSelect;

import('plugins.generic.dataverse.classes.DataverseMetadata');

define('FORM_DATASET_METADATA', 'datasetMetadata');

class DatasetMetadataForm extends FormComponent
{
    public $id = FORM_DATASET_METADATA;

    public function __construct($action, $method, $locales, $dataset)
    {
        $this->action = $action;
        $this->method = $method;
        $this->locales = $locales;

        $this->addField(new FieldText('datasetTitle', [
            'label' => __('plugins.generic.dataverse.metadataForm.title'),
            'isRequired' => true,
            'value' => $dataset->getTitle(),
            'size' => 'large',
        ]))
        ->addField(new FieldRichTextarea('datasetDescription', [
            'label' => __('plugins.generic.dataverse.metadataForm.description'),
            'isRequired' => true,
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
            'plugins' => 'paste,link,lists,image,code',
            'value' => $dataset->getDescription()
        ]))
        ->addField(new FieldControlledVocab('datasetKeywords', [
            'label' => __('plugins.generic.dataverse.metadataForm.keyword'),
            'tooltip' => __('manager.setup.metadata.keywords.description'),
            'apiUrl' => $this->getVocabSuggestionUrlBase(),
            'locales' => $this->locales,
            'selected' => (array) $dataset->getKeywords() ?? [],
            'value' => (array) $dataset->getKeywords() ?? []
        ]))
        ->addField(new FieldSelect('datasetSubject', [
            'label' => __('plugins.generic.dataverse.metadataForm.subject.label'),
            'isRequired' => true,
            'options' => DataverseMetadata::getDataverseSubjects(),
            'value' => $dataset->getSubject(),
        ]))
        ->addField(new FieldSelect('datasetLicense', [
            'label' => __('plugins.generic.dataverse.metadataForm.license.label'),
            'isRequired' => true,
            'options' => DataverseMetadata::getDataverseLicenses($this->getDataverseConfiguration()),
            'value' => $dataset->getLicense(),
        ]));
    }

    private function getVocabSuggestionUrlBase()
    {
        $request = Application::get()->getRequest();
        $contextPath = $request->getContext()->getPath();
        return $request->getDispatcher()->url($request, ROUTE_API, $contextPath, 'vocabs', null, null, ['vocab' => 'submissionKeyword']);
    }

    private function getDataverseConfiguration(): DataverseConfiguration
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $configurationDAO = DAORegistry::getDAO('DataverseConfigurationDAO');
        $configuration = $configurationDAO->get($context->getId());

        return $configuration;
    }
}
