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
        $this->locales = $this->getFormLocales();

        $dataverseMetadata = new DataverseMetadata();
        $dataverseLicenses = $dataverseMetadata->getDataverseLicenses();

        if ($page == 'workflow') {
            $this->addField(new FieldText('datasetTitle', [
                'label' => __('plugins.generic.dataverse.metadataForm.title'),
                'isRequired' => true,
                'value' => $dataset->getTitle(),
                'size' => 'large',
            ]))
            ->addField(new FieldRichTextarea('datasetDescription', [
                'label' => __('plugins.generic.dataverse.metadataForm.description'),
                'isRequired' => true,
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => 'paste,link,lists,image,code',
                'value' => $dataset->getDescription()
            ]))
            ->addField(new FieldControlledVocab('datasetKeywords', [
                'label' => __('plugins.generic.dataverse.metadataForm.keyword'),
                'tooltip' => __('manager.setup.metadata.keywords.description'),
                'apiUrl' => $this->getVocabSuggestionUrlBase(),
                'locales' => $this->locales,
                'selected' => (array) $dataset->getKeywords() ?? [],
                'value' => (array) $dataset->getKeywords() ?? []
            ]));
        }

        $this->addField(new FieldSelect('datasetSubject', [
            'label' => __('plugins.generic.dataverse.metadataForm.subject.label'),
            'isRequired' => true,
            'options' => $dataverseMetadata->getDataverseSubjects(),
            'value' => $dataset->getSubject(),
        ]))
        ->addField(new FieldSelect('datasetLicense', [
            'label' => __('plugins.generic.dataverse.metadataForm.license.label'),
            'isRequired' => true,
            'options' => $this->mapLicensesForDisplay($dataverseLicenses),
            'value' => $dataset->getLicense(),
        ]));
    }

    private function getVocabSuggestionUrlBase()
    {
        $request = Application::get()->getRequest();
        $contextPath = $request->getContext()->getPath();
        return $request->getDispatcher()->url($request, Application::ROUTE_API, $contextPath, 'vocabs', null, null, ['vocab' => 'submissionKeyword']);
    }

    private function getFormLocales(): array
    {
        $context = Application::get()->getRequest()->getContext();
        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = array_map(fn ($localeMetadata) => $localeMetadata->getDisplayName(), Locale::getLocales());

        $formLocales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        return $formLocales;
    }

    private function mapLicensesForDisplay(array $licenses): array
    {
        $mappedLicenses = [];
        foreach($licenses as $license) {
            $mappedLicenses[] = ['label' => $license['name'], 'value' => $license['name']];
        }
        return $mappedLicenses;
    }
}
