<?php

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

import('plugins.generic.dataverse.classes.services.DataStatementService');

define('FORM_DATA_STATEMENT', 'dataStatement');

class DataStatementForm extends FormComponent
{
    public $id = FORM_DATA_STATEMENT;

    public $method = 'PUT';

    public function __construct($action, $locales, $publication)
    {
        $this->action = $action;
        $this->locales = $locales;

        $dataStatementService = new DataStatementService();
        $dataStatementOptions = $this->getDataStatementOptions($dataStatementService);
        $dataverseName = $dataStatementService->getDataverseName();

        $request = Application::get()->getRequest();
        $contextPath = $request->getContext()->getPath();
        $vocabApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $contextPath, 'vocabs');

        import('plugins.generic.dataverse.classes.components.forms.FieldControlledVocabUrl');
        $this->addField(new FieldOptions('dataStatementTypes', [
            'label' => __('plugins.generic.dataverse.dataStatement.title'),
            'isRequired' => true,
            'value' => $publication->getData('dataStatementTypes') ?? [],
            'options' => $dataStatementOptions,
        ]))
        ->addField(new FieldControlledVocabUrl('dataStatementUrls', [
            'label' => __('plugins.generic.dataverse.dataStatement.repoAvailable.urls'),
            'description' => __('plugins.generic.dataverse.dataStatement.repoAvailable.urls.description'),
            'apiUrl' => $vocabApiUrl,
            'selected' => $publication->getData('dataStatementUrls') ?? [],
        ]))
        ->addField(new FieldText('dataStatementReason', [
            'label' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason'),
            'isRequired' => true,
            'isMultilingual' => true,
            'value' => $publication->getData('dataStatementReason'),
            'size' => 'large',
        ]))
        ->addField(new FieldOptions('researchDataSubmitted', [
            'label' => __('plugins.generic.dataverse.researchData'),
            'options' => [
                [
                    'value' => true,
                    'label' => __('plugins.generic.dataverse.dataStatement.researchDataSubmitted', [
                        'dataverseName' => $dataverseName,
                    ]),
                    'disabled' => true,
                ],
            ],
            'value' => $this->hasDataset($publication),
        ]));
    }

    private function getDataStatementOptions(): array
    {
        $dataStatementService = new DataStatementService();
        $dataStatementTypes = $dataStatementService->getDataStatementTypes();
        unset($dataStatementTypes[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]);

        $dataStatementOptions = array_map(function ($value, $label) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        }, array_keys($dataStatementTypes), array_values($dataStatementTypes));

        return $dataStatementOptions;
    }

    private function hasDataset(Publication $publication): bool
    {
        $studyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $studyDAO->getStudyBySubmissionId($publication->getData('submissionId'));

        if (is_null($study)) {
            return false;
        }

        return true;
    }
}
