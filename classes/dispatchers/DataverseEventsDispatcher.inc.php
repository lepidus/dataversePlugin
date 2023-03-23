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
        HookRegistry::register('Dispatcher::dispatch', array($this, 'setupDataverseAPIHandlers'));
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

        import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
        $datasetFactory = new SubmissionDatasetFactory($submission);
        $dataset = $datasetFactory->getDataset();

        if (empty($dataset->getFiles())) {
            return false;
        }

        import('plugins.generic.dataverse.classes.dataverseAPI.packagers.NativeAPIDatasetPackager');
        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->createDatasetPackage();
        $datasetPackagePath = $packager->getPackagePath();

        $dataverseConfig = DAORegistry::getDAO('DataverseCredentialsDAO')->get($submission->getContextId());

        import('plugins.generic.dataverse.classes.dataverseAPI.DataverseNativeAPI');
        $dataverseAPI = new DataverseNativeAPI();
        $dataverseAPI->configure($dataverseConfig);

        try {
            $datasetIdentifier = $dataverseAPI->getCollectionOperations()->createDataset($datasetPackagePath);
            foreach ($dataset->getFiles() as $file) {
                $dataverseAPI->getDatasetOperations()->addFile($datasetIdentifier->getPersistentId(), $file);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }

        $swordAPIBaseUrl = $dataverseConfig->getDataverseServerUrl() . '/dvn/api/data-deposit/v1.1/swordv2/';
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->newDataObject();
        $study->setSubmissionId($submission->getId());
        $study->setPersistentId($datasetIdentifier->getPersistentId());
        $study->setEditUri($swordAPIBaseUrl . 'edit/study/' . $datasetIdentifier->getPersistentId());
        $study->setEditMediaUri($swordAPIBaseUrl . 'edit-media/study/' . $datasetIdentifier->getPersistentId());
        $study->setStatementUri($swordAPIBaseUrl . 'statement/study/' . $datasetIdentifier->getPersistentId());
        $study->setPersistentUri('https://doi.org/' . str_replace('doi:', '', $datasetIdentifier->getPersistentId()));
        $dataverseStudyDAO->insertStudy($study);

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

    public function setupDataverseAPIHandlers(string $hookname, Request $request): void
    {
        $router = $request->getRouter();
        if (!($router instanceof \APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/datasets')) {
            $this->plugin->import('api.v1.datasets.DatasetHandler');
            $handler = new DatasetHandler();
        } elseif (str_contains($request->getRequestPath(), 'api/v1/draftDatasetFiles')) {
            $this->plugin->import('api.v1.draftDatasetFiles.DraftDatasetFileHandler');
            $handler = new DraftDatasetFileHandler();
        }

        if (!isset($handler)) {
            return;
        }

        $router->setHandler($handler);
        $handler->getApp()->run();
        exit;
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
