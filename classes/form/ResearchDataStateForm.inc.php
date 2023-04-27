<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;

class ResearchDataStateForm extends FormComponent
{
    public $id = 'researchDataState';

    public $method = 'PUT';

    public function __construct($action, $locales, $publication)
    {
        $this->action = $action;
        $this->locales = $locales;

        import('plugins.generic.dataverse.classes.services.ResearchDataStateService');
        $researchDataStateService = new ResearchDataStateService();
        $researchDataStates = $researchDataStateService->getResearchDataStates();
        $researchDataStateOptions = array_map(function ($value, $label) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        }, array_keys($researchDataStates), array_values($researchDataStates));

        $this->addField(new FieldOptions('researchDataState', [
                    'label' => __('plugins.generic.dataverse.researchDataState.state'),
                    'type' => 'radio',
                    'value' => $publication->getData('researchDataState'),
                    'options' => $researchDataStateOptions,
                    'isRequired' => true,
                ]))
                ->addField(new FieldText('researchDataUrl', [
                    'label' => __('plugins.generic.dataverse.researchDataState.repoAvailable.url'),
                    'value' => $publication->getData('researchDataUrl'),
                    'size' => 'large',
                    'showWhen' => ['researchDataState', RESEARCH_DATA_REPO_AVAILABLE],
                ]))
                ->addField(new FieldText('researchDataReason', [
                    'label' => __('plugins.generic.dataverse.researchDataState.private.reason'),
                    'value' => $publication->getData('researchDataReason'),
                    'size' => 'large',
                    'showWhen' => ['researchDataState', RESEARCH_DATA_PRIVATE],
                ]));
    }
}
