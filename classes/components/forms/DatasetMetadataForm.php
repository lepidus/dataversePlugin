<?php

namespace APP\plugins\generic\dataverse\classes\components\forms;

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldControlledVocab;
use PKP\components\forms\FieldSelect;
use APP\core\Application;
use PKP\facades\Locale;
use APP\plugins\generic\dataverse\classes\DataverseMetadata;

class DatasetMetadataForm extends FormComponent
{
    public function __construct($action, $method, $dataset, $page)
    {
        $this->id = 'datasetMetadata';
        $this->action = $action;
        $this->method = $method;
        $this->locales = $this->mapCurrentLocale();

        $dataverseMetadata = new DataverseMetadata();
        $dataverseLicenses = [];
        $datasetMetadata = $this->getDatasetMetadata($dataset);

        if ($page == 'submission') {
            $dataverseLicenses = $dataverseMetadata->getDataverseLicenses();

            if (empty($datasetMetadata['license'])) {
                $datasetMetadata['license'] = $dataverseMetadata->getDefaultLicense();
            }
        }

        if ($page == 'workflow') {
            $this->addField(new FieldText('datasetTitle', [
                'label' => __('plugins.generic.dataverse.metadataForm.title'),
                'isRequired' => true,
                'value' => $datasetMetadata['title'],
                'size' => 'large',
            ]))
            ->addField(new FieldRichTextarea('datasetDescription', [
                'label' => __('plugins.generic.dataverse.metadataForm.description'),
                'isRequired' => true,
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => 'paste,link,lists,image,code',
                'value' => $datasetMetadata['description']
            ]))
            ->addField(new FieldControlledVocab('datasetKeywords', [
                'label' => __('plugins.generic.dataverse.metadataForm.keyword'),
                'tooltip' => __('manager.setup.metadata.keywords.description'),
                'apiUrl' => $this->getVocabSuggestionUrlBase(),
                'isMultilingual' => true,
                'locales' => $this->locales,
                'value' => $datasetMetadata['keywords']
            ]));
        }

        $this->addField(new FieldSelect('datasetSubject', [
            'label' => __('plugins.generic.dataverse.metadataForm.subject.label'),
            'description' => ($page == 'submission' ? __('plugins.generic.dataverse.metadataForm.subject.description') : ''),
            'isRequired' => true,
            'options' => $dataverseMetadata->getDataverseSubjects(),
            'value' => $datasetMetadata['subject'],
        ]))
        ->addField(new FieldSelect('datasetLicense', [
            'label' => __('plugins.generic.dataverse.metadataForm.license.label'),
            'description' => ($page == 'submission' ? __('plugins.generic.dataverse.metadataForm.license.description') : ''),
            'isRequired' => true,
            'options' => $this->mapLicensesForDisplay($dataverseLicenses),
            'value' => $datasetMetadata['license'],
        ]));
    }

    private function getDatasetMetadata($dataset)
    {
        if (is_null($dataset)) {
            return [
                'title' => '',
                'description' => '',
                'keywords' => [],
                'subject' => '',
                'license' => ''
            ];
        }

        $mappedKeywords = (array) $dataset->getKeywords() ?? [];
        $mappedKeywords = [Locale::getLocale() => $mappedKeywords];

        return [
            'title' => $dataset->getTitle(),
            'description' => $dataset->getDescription(),
            'keywords' => $mappedKeywords,
            'subject' => $dataset->getSubject(),
            'license' => $dataset->getLicense()
        ];
    }

    private function getVocabSuggestionUrlBase()
    {
        $request = Application::get()->getRequest();
        $contextPath = $request->getContext()->getPath();
        return $request->getDispatcher()->url($request, Application::ROUTE_API, $contextPath, 'vocabs', null, null, ['vocab' => 'submissionKeyword']);
    }

    private function mapCurrentLocale(): array
    {
        $localeKey = Locale::getLocale();
        $localeNames = array_map(fn ($localeMetadata) => $localeMetadata->getDisplayName(), Locale::getLocales());

        return [
            ['key' => $localeKey, 'label' => $localeNames[$localeKey]]
        ];
    }

    private function mapLicensesForDisplay(array $licenses): array
    {
        $mappedLicenses = [];
        foreach ($licenses as $license) {
            $mappedLicenses[] = ['label' => $license['name'], 'value' => $license['name']];
        }
        return $mappedLicenses;
    }

    public function getConfig()
    {
        $config = parent::getConfig();
        $config['primaryLocale'] = Locale::getLocale();

        return $config;
    }
}
