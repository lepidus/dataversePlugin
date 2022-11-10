<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;

define('FORM_DATASET_METADATA', 'datasetMetadata');

class DatasetMetadataForm extends FormComponent {

    public $id = FORM_DATASET_METADATA;

	public $method = 'PUT';

	public function __construct($action, $locales) {
		$this->action = $action;
		$this->locales = $locales;

        $this->addField(new FieldText('title', [
            'label' => __('plugins.generic.dataverse.metadataForm.title'),
            'value' => null,
        ]))
        ->addField(new FieldText('description', [
            'label' => __('plugins.generic.dataverse.metadataForm.description'),
            'value' => null,
        ]))
        ->addField(new FieldText('keyword', [
            'label' => __('plugins.generic.dataverse.metadataForm.keyword'),
            'value' => null,
        ]));
	}
}