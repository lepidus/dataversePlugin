<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Plugin;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;

abstract class DataverseDispatcher
{
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    abstract protected function registerHooks(): void;

    protected function assignDataStatementConstants($templateMgr): void
    {
        $templateMgr->assign((new DataStatementService())->getConstantsForTemplates());
    }

    protected function getSubmissionLocales($context, $submission): array
    {
        $localeNames = $context->getSupportedSubmissionMetadataLocaleNames()
            + $submission->getPublicationLanguageNames();

        return collect($localeNames)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->sortBy('key')
            ->values()
            ->toArray();
    }

    protected function localizeFormConfig(array $config, string $submissionLocale, array $locales): array
    {
        $config['primaryLocale'] = $submissionLocale;
        $config['visibleLocales'] = [$submissionLocale];
        $config['supportedFormLocales'] = collect($locales)
            ->sortBy([fn (array $a, array $b) => $b['key'] === $submissionLocale ? 1 : -1])
            ->values()
            ->toArray();

        return $config;
    }

    protected function addPluginAssets(TemplateManager $templateMgr): void
    {
        $templateMgr->addJavaScript(
            'dataversePlugin',
            $this->plugin->getPluginFullPath() . '/public/build/build.iife.js',
            [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
            ]
        );

        $templateMgr->addStyleSheet(
            'dataversePlugin',
            $this->plugin->getPluginFullPath() . '/public/build/build.css',
            ['contexts' => ['backend']]
        );
    }

    protected function getApiUrl(string $handlerPath, array $params = []): ?string
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            $handlerPath,
            null,
            null,
            $params
        );
    }
}
