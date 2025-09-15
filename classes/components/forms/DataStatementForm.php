<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
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
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $this->action = $action;
        $this->locales = $this->getFormLocales($context);

        $dataStatementOptions = $this->getDataStatementOptions($page);

        $this->dataversePluginApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'dataverse');
        $vocabApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'vocabs');

        $this->addField(new FieldOptions('dataStatementTypes', [
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
        ->addField(new FieldText('dataStatementReason', [
            'label' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason'),
            'description' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason.description'),
            'isMultilingual' => true,
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
                            'dataverseName' => '',
                        ]),
                        'disabled' => true,
                    ],
                ],
                'value' => $this->hasDataset($publication),
            ]));
        }
    }

    public function getConfig()
    {
        $config = parent::getConfig();

        $config = array_merge(
            $config,
            [
                'dataversePluginApiUrl' => $this->dataversePluginApiUrl
            ]
        );

        return $config;
    }

    private function getFormLocales($context): array
    {
        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = array_map(fn ($localeMetadata) => $localeMetadata->getDisplayName(), Locale::getLocales());

        $formLocales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        return $formLocales;
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
