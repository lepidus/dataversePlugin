<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;

import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');

define('FORM_DATASET_METADATA', 'datasetMetadata');

class DatasetMetadataForm extends FormComponent {

    public $id = FORM_DATASET_METADATA;

	public $method = 'PUT';

	public function __construct($action, $locales, $datasetResponse) {
		$this->action = $action;
		$this->locales = $locales;

        $metadataBlocks = $datasetResponse->data->latestVersion->metadataBlocks->citation->fields;
        $datasetDataCreator = new DataverseDatasetDataCreator();
        $datasetData = $datasetDataCreator->create($metadataBlocks);

        $this->addField(new FieldText('title', [
            'label' => __('plugins.generic.dataverse.metadataForm.title'),
            'value' => $datasetData->getData('title'),
        ]))
        ->addField(new FieldText('description', [
            'label' => __('plugins.generic.dataverse.metadataForm.description'),
            'value' => $datasetData->getData('dsDescription'),
        ]))
        ->addField(new FieldText('keyword', [
            'label' => __('plugins.generic.dataverse.metadataForm.keyword'),
            'value' => $datasetData->getData('keyword'),
        ]));
	}
}