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

        $publication = $this->fixDataStatementTypesConversion($publication);
        $dataStatementTypes = $this->getDataStatementTypes();

        $dataStatementOptions = array_map(function ($value, $label) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        }, array_keys($dataStatementTypes), array_values($dataStatementTypes));

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
                        'dataverseName' => $this->getDataverseName(),
                    ]),
                    'disabled' => true,
                ],
            ],
            'value' => in_array(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $publication->getData('dataStatementTypes')),
        ]));
    }

    private function fixDataStatementTypesConversion($publication): Publication
    {
        $dataStatementTypes = $publication->getData('dataStatementTypes');

        if (is_array($dataStatementTypes)) {
            sort($dataStatementTypes);

            Services::get('publication')->edit(
                $publication,
                ['dataStatementTypes' => $dataStatementTypes],
                \Application::get()->getRequest()
            );
        }

        return Services::get('publication')->get($publication->getId());
    }

    private function getDataStatementTypes(): array
    {
        $dataStatementService = new DataStatementService();
        $dataStatementTypes = $dataStatementService->getDataStatementTypes();
        unset($dataStatementTypes[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]);

        return $dataStatementTypes;
    }

    private function getDataverseName(): string
    {
        import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
        $dataverseClient = new DataverseClient();
        $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();

        return $dataverseCollection->getName();
    }
}
