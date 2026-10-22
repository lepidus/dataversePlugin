<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use APP\core\Application;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\components\forms\FieldControlledVocabUrl;
use APP\plugins\generic\dataverse\classes\components\forms\FieldDataStatementReason;
use APP\plugins\generic\dataverse\classes\components\forms\FieldDataStatementTypes;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DataStatementForm extends FormComponent
{
    public $id = 'dataStatement';
    public $method = 'PUT';

    public function __construct($action, $publication, $page, ?array $locales = null)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $this->action = $action;
        $this->locales = $locales ?? $this->getFormLocales($context);

        $dataStatementOptions = $this->getDataStatementOptions($page);

        $vocabApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'vocabs');

        $this->addField(new FieldDataStatementTypes('dataStatementTypes', [
            'label' => __('plugins.generic.dataverse.dataStatement.title'),
            'isRequired' => true,
            'value' => $publication->getData('dataStatementTypes') ?? [],
            'options' => $dataStatementOptions,
        ]))
        ->addField(new FieldControlledVocabUrl('dataStatementUrls', [
            'label' => __('plugins.generic.dataverse.dataStatement.repoAvailable.urls'),
            'description' => __('plugins.generic.dataverse.dataStatement.repoAvailable.urls.description'),
            'isRequired' => true,
            'apiUrl' => $vocabApiUrl,
            'value' => $publication->getData('dataStatementUrls') ?? [],
        ]))
        ->addField(new FieldDataStatementReason('dataStatementReason', [
            'label' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason'),
            'description' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason.description'),
            'isMultilingual' => true,
            'isRequired' => true,
            'value' => $publication->getData('dataStatementReason'),
            'size' => 'large'
        ]));

        if ($page == 'workflow') {
            $this->addField(new FieldOptions('researchDataSubmitted', [
                'label' => __('plugins.generic.dataverse.researchData'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('plugins.generic.dataverse.dataStatement.researchDataSubmitted', [
                            'dataverseName' => (new DataStatementService())->getDataverseName() ?? '',
                        ]),
                        'disabled' => true,
                    ],
                ],
                'value' => $this->hasDataset($publication),
            ]));
        }
    }

    private function getFormLocales($context): array
    {
        $localeNames = $context->getSupportedSubmissionMetadataLocaleNames();

        return array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label],
            array_keys($localeNames),
            array_values($localeNames)
        );
    }

    private function getDataStatementOptions($page): array
    {
        $dataStatementService = new DataStatementService();
        $includeSubmittedType = ($page == 'submission');
        $dataStatementTypes = $dataStatementService->getDataStatementTypes($includeSubmittedType);

        return array_map(function ($value, $label) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        }, array_keys($dataStatementTypes), array_values($dataStatementTypes));
    }

    private function hasDataset($publication): bool
    {
        $study = Repo::dataverseStudy()->getBySubmissionId($publication->getData('submissionId'));

        return !is_null($study);
    }
}
