<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldControlledVocab;

import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');

define('FORM_DATASET_METADATA', 'datasetMetadata');

class DatasetMetadataForm extends FormComponent {

    public $id = FORM_DATASET_METADATA;

	public $method = 'PUT';

	public function __construct($action, $locales, $datasetResponse, $vocabSuggestionUrlBase) {
		$this->action = $action;
		$this->locales = $locales;

        $datasetData = new DataverseDatasetData();
        if (!empty($datasetResponse)) {
            $metadataBlocks = $datasetResponse->data->latestVersion->metadataBlocks->citation->fields;
            $datasetDataCreator = new DataverseDatasetDataCreator();
            $datasetData = $datasetDataCreator->createDatasetData($metadataBlocks);
        }

        $this->addField(new FieldText('datasetTitle', [
            'label' => __('plugins.generic.dataverse.metadataForm.title'),
            'isRequired' => true,
            'value' => $datasetData->getData('title'),
            'size' => 'large',
        ]))
        ->addField(new FieldRichTextarea('datasetDescription', [
            'label' => __('plugins.generic.dataverse.metadataForm.description'),
            'isRequired' => true,
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
            'plugins' => 'paste,link,lists,image,code',
            'value' => implode($datasetData->getData('dsDescription')),
        ]))
        ->addField(new FieldControlledVocab('datasetKeywords', [
            'label' => __('plugins.generic.dataverse.metadataForm.keyword'),
            'tooltip' => __('manager.setup.metadata.keywords.description'),
            'apiUrl' => $vocabSuggestionUrlBase,
            'locales' => $this->locales,
            'selected' => (array) $datasetData->getData('keyword'),
        ]));
	}
}