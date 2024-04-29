<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldControlledVocab;
use APP\core\Application;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\components\forms\FieldControlledVocabUrl;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DataStatementForm extends FormComponent
{
    public $id = 'dataStatement';
    public $method = 'PUT';

    public function __construct($action, $publication, $page)
    {
        $this->action = $action;

        $dataStatementTypes = $this->getDataStatementTypes($page);
        $dataStatementOptions = array_map(function ($value, $label) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        }, array_keys($dataStatementTypes), array_values($dataStatementTypes));

        $request = Application::get()->getRequest();
        $contextPath = $request->getContext()->getPath();
        $vocabApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $contextPath, 'vocabs');

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
            'value' => $publication->getData('dataStatementUrls') ?? [],
        ]))
        ->addField(new FieldText('dataStatementReason', [
            'label' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason'),
            'isRequired' => true,
            'value' => $publication->getData('dataStatementReason'),
            'size' => 'large',
        ]));

        if ($page == 'workflow') {
            $this->addField(new FieldOptions('researchDataSubmitted', [
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
                'value' => $this->hasDataset($publication),
            ]));
        }
    }

    private function getDataStatementTypes($page): array
    {
        $dataStatementService = new DataStatementService();
        $dataStatementTypes = $dataStatementService->getDataStatementTypes();

        if ($page == 'workflow') {
            unset($dataStatementTypes[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]);
        }

        return $dataStatementTypes;
    }

    private function getDataverseName(): string
    {
        $dataverseClient = new DataverseClient();
        $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();

        return $dataverseCollection->getName();
    }

    private function hasDataset($publication): bool
    {
        $study = Repo::dataverseStudy()->getBySubmissionId($publication->getData('submissionId'));

        return !is_null($study);
    }
}
