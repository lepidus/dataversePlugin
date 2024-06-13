<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class SelectDataFilesForReviewForm extends FormComponent
{
    public $id = 'selectDataFilesReview';
    public $action = FormComponent::ACTION_EMIT;

    public function __construct()
    {
        $this->addField(new FieldText('dataStatementReason', [
            'label' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason'),
            'description' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason.description'),
            'isRequired' => true,
            'isMultilingual' => true,
            'value' => '',
            'size' => 'large',
        ]));
    }
}
