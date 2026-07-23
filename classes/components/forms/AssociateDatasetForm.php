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

        $this->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('plugins.generic.dataverse.associateDatasetForm.submitButton')
            ],
        ]);
        $this->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ]);
        $this->addField(new FieldText('datasetPersistentId', [
            'groupId' => 'default',
            'label' => __('plugins.generic.dataverse.associateDatasetForm.persistentId.label'),
            'description' => __('plugins.generic.dataverse.associateDatasetForm.persistentId.description'),
            'isRequired' => true,
            'multilingual' => false,
        ]));
    }
}
