<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;

class AssociateDatasetForm extends FormComponent
{
    public function __construct($action)
    {
        $this->id = 'associateDataset';
        $this->action = $action;
        $this->method = 'POST';
        //$this->locales = $this->mapCurrentLocale();

        $this->addField(new FieldText('datasetPersistentId', [
            'label' => __('plugins.generic.dataverse.associateDatasetForm.persistentId.label'),
            'description' => __('plugins.generic.dataverse.associateDatasetForm.persistentId.description'),
            'isRequired' => true,
            'multilingual' => false,
        ]));
    }
}
