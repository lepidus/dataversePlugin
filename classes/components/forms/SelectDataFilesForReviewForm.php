<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldOptions;

class SelectDataFilesForReviewForm extends FormComponent
{
    public $id = 'selectDataFilesReview';
    public $action = FormComponent::ACTION_EMIT;

    public function __construct(array $datasetFiles)
    {
        $datasetFilesOptions = array_map(function ($file) {
            return [
                'value' => $file->getId(),
                'label' => $file->getFileName(),
            ];
        }, $datasetFiles);

        $this->addField(new FieldOptions('selectedDataFilesForReview', [
            'label' => __('plugins.generic.dataverse.decision.selectDataFiles.name'),
            'options' => $datasetFilesOptions,
        ]));
    }
}
