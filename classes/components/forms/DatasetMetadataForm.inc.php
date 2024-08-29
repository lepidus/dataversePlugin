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

        $dataverseMetadata = new DataverseMetadata();
        $dataverseLicenses = $dataverseMetadata->getDataverseLicenses();

        $datasetMetadata = $this->getDatasetMetadata($dataset);

        $this->addField(new FieldText('datasetTitle', [
            'label' => __('plugins.generic.dataverse.metadataForm.title'),
            'isRequired' => true,
            'value' => $datasetMetadata['title'],
            'size' => 'large',
        ]))
        ->addField(new FieldRichTextarea('datasetDescription', [
            'label' => __('plugins.generic.dataverse.metadataForm.description'),
            'isRequired' => true,
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
            'plugins' => 'paste,link,lists,image,code',
            'value' => $datasetMetadata['description']
        ]))
        ->addField(new FieldControlledVocab('datasetKeywords', [
            'label' => __('plugins.generic.dataverse.metadataForm.keyword'),
            'tooltip' => __('manager.setup.metadata.keywords.description'),
            'apiUrl' => $this->getVocabSuggestionUrlBase(),
            'locales' => $this->locales,
            'selected' => $datasetMetadata['keywords'],
            'value' => $datasetMetadata['keywords']
        ]))
        ->addField(new FieldSelect('datasetSubject', [
            'label' => __('plugins.generic.dataverse.metadataForm.subject.label'),
            'isRequired' => true,
            'options' => $dataverseMetadata->getDataverseSubjects(),
            'value' => $datasetMetadata['subject'],
        ]))
        ->addField(new FieldSelect('datasetLicense', [
            'label' => __('plugins.generic.dataverse.metadataForm.license.label'),
            'isRequired' => true,
            'options' => $this->mapLicensesForDisplay($dataverseLicenses),
            'value' => $datasetMetadata['license'],
        ]));
    }

    private function getDatasetMetadata($dataset)
    {
        if (is_null($dataset)) {
            return [
                'title' => '',
                'description' => '',
                'keywords' => [],
                'subject' => '',
                'license' => ''
            ];
        }

        return [
            'title' => $dataset->getTitle(),
            'description' => $dataset->getDescription(),
            'keywords' => (array) $dataset->getKeywords() ?? [],
            'subject' => $dataset->getSubject(),
            'license' => $dataset->getLicense()
        ];
    }

    private function getVocabSuggestionUrlBase()
    {
        $request = Application::get()->getRequest();
        $contextPath = $request->getContext()->getPath();
        return $request->getDispatcher()->url($request, ROUTE_API, $contextPath, 'vocabs', null, null, ['vocab' => 'submissionKeyword']);
    }

    private function mapLicensesForDisplay(array $licenses): array
    {
        $mappedLicenses = [];
        foreach($licenses as $license) {
            $mappedLicenses[] = ['label' => $license['name'], 'value' => $license['name']];
        }
        return $mappedLicenses;
    }
}
