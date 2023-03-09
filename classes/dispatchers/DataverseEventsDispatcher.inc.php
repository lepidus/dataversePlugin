<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DataverseEventsDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'datasetDepositOnSubmission'));
        HookRegistry::register('Schema::get::draftDatasetFile', array($this, 'loadDraftDatasetFileSchema'));
        HookRegistry::register('Schema::get::submission', array($this, 'modifySubmissionSchema'));
        HookRegistry::register('LoadComponentHandler', array($this, 'setupDataverseHandlers'));
        HookRegistry::register('Dispatcher::dispatch', array($this, 'setupDatasetsHandler'));
        HookRegistry::register('Publication::publish', array($this, 'publishDeposit'), HOOK_SEQUENCE_CORE);
    }

    public function getDataverseConfiguration(): DataverseConfiguration
    {
        $context = $this->plugin->getRequest()->getContext();
        $contextId = $context->getId();

        import('plugins.generic.dataverse.classes.DataverseConfiguration');
        return new DataverseConfiguration(
            $this->plugin->getSetting($contextId, 'dataverseUrl'),
            $this->plugin->getSetting($contextId, 'apiToken')
        );
    }

    public function getDataverseService(): DataverseService
    {
        import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');
        $serviceFactory = new DataverseServiceFactory();
        $service = $serviceFactory->build($this->getDataverseConfiguration(), $this->plugin);
        return $service;
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

    public function datasetDepositOnSubmission(string $hookName, array $params): bool
    {
        $form =& $params[0];
        $submission = $form->submission;
        $submissionUser = $this->getCurrentUser();

        $service = $this->getDataverseService();
        $service->setSubmission($submission, $submissionUser);
        $service->depositPackage();
        return false;
    }

    public function publishDeposit(string $hookName, array $params): void
    {
        $submission = $params[2];
        $submissionUser = $this->getCurrentUser();

        $service = $this->getDataverseService();
        $service->setSubmission($submission, $submissionUser);
        $service->releaseStudy();
    }

    private function getCurrentUser(): User
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();
        return $currentUser;
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
            case 'plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridHandler':
                return true;
                break;
        }
        return false;
    }
}
