<?php

import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');
import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');

class DataverseServiceDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
    {
        HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'dataverseDepositOnSubmission'));
        HookRegistry::register('Schema::get::draftDatasetFile', array($this, 'loadDraftDatasetFileSchema'));
        HookRegistry::register('Dispatcher::dispatch', array($this, 'setupDraftDatasetFileHandler'));
        HookRegistry::register('Schema::get::submission', array($this, 'modifySubmissionSchema'));
        HookRegistry::register('LoadComponentHandler', array($this, 'setupDataverseHandlers'));
        HookRegistry::register('Dispatcher::dispatch', array($this, 'setupDatasetsHandler'));
        HookRegistry::register('Publication::publish', array($this, 'publishDeposit'), HOOK_SEQUENCE_CORE);

        parent::__construct($plugin);
    }

    public function modifySubmissionSchema(string $hookName, array $params): bool
    {
        $schema =& $params[0];
        $schema->properties->{'datasetSubject'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    public function dataverseDepositOnSubmission(string $hookName, array $params): void
    {
        $form =& $params[0];
        $submission = $form->submission;

        $service = $this->getDataverseService();
        $service->setSubmission($submission);
        $service->depositPackage();
    }

    public function publishDeposit(string $hookName, array $params): void
    {
        $submission = $params[2];

        $service = $this->getDataverseService();
        $service->setSubmission($submission);
        $service->releaseStudy();
    }

    public function setupDraftDatasetFileHandler(string $hookname, Request $request): bool
    {
        $router = $request->getRouter();
        if ($router instanceof \APIRouter && str_contains($request->getRequestPath(), 'api/v1/draftDatasetFiles')) {
            $this->plugin->import('api.v1.draftDatasetFiles.DraftDatasetFileHandler');
            $handler = new DraftDatasetFileHandler();
            $router->setHandler($handler);
            $handler->getApp()->run();
            exit;
        }
        return false;
    }

    public function setupDatasetsHandler(string $hookname, Request $request): bool
    {
        $router = $request->getRouter();
        if ($router instanceof \APIRouter && str_contains($request->getRequestPath(), 'api/v1/datasets')) {
            $this->plugin->import('api.v1.datasets.DatasetsHandler');
            $handler = new DatasetsHandler();
            $router->setHandler($handler);
            $handler->getApp()->run();
            exit;
        }
        return false;
    }

    public function loadDraftDatasetFileSchema($hookname, $params): bool
    {
        $schema = &$params[0];
        $draftDatasetFileSchemaFile = BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/draftDatasetFile.json';

        if (file_exists($draftDatasetFileSchemaFile)) {
            $schema = json_decode(file_get_contents($draftDatasetFileSchemaFile));
            if (!$schema) {
                fatalError('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $draftDatasetFileSchemaFile . '. Last JSON error: ' . json_last_error());
            }
        }

        return false;
    }

    public function setupDataverseHandlers($hookName, $params): bool
    {
        $component =& $params[0];
        switch ($component) {
            case 'plugins.generic.dataverse.handlers.DraftDatasetFileUploadHandler':
                return true;
                break;
        }
        return false;
    }
}
