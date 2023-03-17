<?php

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.log.SubmissionLog');
import('classes.log.SubmissionEventLogEntry');

class DatasetsHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = 'datasets';
        $roles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR];
        $this->_endpoints = array(
            'PUT' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => array($this, 'edit'),
                    'roles' => $roles
                ),
            ),
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'addDataset'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => array($this, 'addFile'),
                    'roles' => $roles
                ),
            ),
            'GET' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/files',
                    'handler' => array($this, 'getFiles'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => array($this, 'downloadDatasetFile'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => array($this, 'getCitation'),
                    'roles' => $roles
                ),
            ),
            'DELETE' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => array($this, 'deleteFile'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => array($this, 'deleteDataset'),
                    'roles' => $roles
                ),
            )
        );
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.PolicySet');
        $rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

        import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function edit($slimRequest, $response, $args)
    {
        $studyId = $args['studyId'];

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy($studyId);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $requestParams = $slimRequest->getParsedBody();

        $service = $this->getDataverseService($this->getRequest());
        $datasetResponse = $service->getDatasetResponse($study);

        import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');
        $datasetDataCreator = new DataverseDatasetDataCreator();

        $metadataBlocks = $datasetResponse->data->latestVersion->metadataBlocks->citation->fields;
        $datasetSubject = $datasetDataCreator->getMetadata($metadataBlocks, 'subject');

        if ($requestParams['datasetSubject'] == $datasetSubject[0]) {
            unset($requestParams['datasetSubject']);
        }

        $datasetMetadataFields = $datasetDataCreator->createMetadataFields($requestParams);
        $datasetMetadata = json_encode($datasetMetadataFields);

        $dataverseResponse = $service->updateDatasetData($datasetMetadata, $study);

        if ($dataverseResponse) {
            return $response->withJson(['message' => 'ok'], 200);
        } else {
            return $response->withStatus(500)->withJsonError('plugins.generic.dataverse.notification.statusInternalServerError');
        }
    }

    public function addDataset($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $queryParams = $slimRequest->getQueryParams();

        $submissionId = $queryParams['submissionId'];
        $draftDatasetFiles = DAORegistry::getDAO('DraftDatasetFileDAO')->getBySubmissionId($submissionId);

        if (empty($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.researchDataFile.error');
        }

        $submission = Services::get('submission')->get($submissionId);
        import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
        $datasetFactory = new SubmissionDatasetFactory($submission);
        $dataset = $datasetFactory->getDataset();
        $dataset->setTitle($requestParams['datasetTitle']);
        $dataset->setDescription($requestParams['datasetDescription']);
        $dataset->setKeywords((array) $requestParams['datasetKeywords']);
        $dataset->setSubject($requestParams['datasetSubject']);

        try {
            import('plugins.generic.dataverse.classes.dataverseAPI.clients.SWORDAPIClient');
            $swordClient = new SWORDAPIClient($submission->getContextId());
            import('plugins.generic.dataverse.classes.dataverseAPI.services.DepositAPIService');
            $depositService = new DepositAPIService($swordClient);
            $depositResponse = $depositService->depositDataset($dataset);
            $dataset->setPersistentId($depositResponse['persistentId']);

            DAORegistry::getDAO('DraftDatasetFileDAO')->deleteBySubmissionId($submissionId);

            import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
            $nativeAPIClient = new NativeAPIClient($submission->getContextId());
            import('plugins.generic.dataverse.classes.dataverseAPI.services.UpdateAPIService');
            $updateService = new UpdateAPIService($nativeAPIClient);
            $updateService->updateDataset($dataset);

            $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
            $study = $dataverseStudyDAO->newDataObject();
            $study->setAllData($depositResponse);
            $study->setSubmissionId($submissionId);
            $dataverseStudyDAO->insertStudy($study);

            $this->registerDatasetEventLog(
                $submissionId,
                SUBMISSION_LOG_SUBMISSION_SUBMIT,
                'plugins.generic.dataverse.log.researchDataDeposited',
                ['persistentURL' => $study->getPersistentUri()]
            );
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $response->withStatus($e->getCode())->withJson(['error' => $e->getMessage()]);
        }

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function addFile($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $request = $this->getRequest();
        $user = $request->getUser();

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);
        $fileId = $requestParams['datasetFile']['temporaryFileId'];

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($fileId, $user->getId());

        $service = $this->getDataverseService($request);
        $dataverseResponse = $service->addDatasetFile($study, $file);

        $datasetFileData = [
            'fileName' => $file->getOriginalFileName()
        ];

        $temporaryFileManager->deleteById($file->getId(), $user->getId());

        if (!$dataverseResponse) {
            return $response->withStatus(500)->withJsonError('plugins.generic.dataverse.notification.statusInternalServerError');
        }

        return $response->withJson($datasetFileData, 200);
    }

    public function getFiles($slimRequest, $response, $args)
    {
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        $datasetFiles = $this->getDatasetFiles($study);

        ksort($datasetFiles);

        return $response->withJson(['items' => $datasetFiles], 200);
    }

    public function getCitation($slimRequest, $response, $args)
    {
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $service = $this->getDataverseService($this->getRequest());

        $citation = $service->getStudyCitation($study);

        $data = ['citation' => $citation];
        return $response->withJson(['data' => $data], 200);
    }

    public function deleteFile($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        $service = $this->getDataverseService($this->getRequest());

        $fileDeleted = $service->deleteDatasetFile($study, $queryParams['fileId']);

        if (!$fileDeleted) {
            return $response->withStatus(500)->withJsonError('plugins.generic.dataverse.notification.statusInternalServerError');
        }

        $items = $this->getDatasetFiles($study);
        return $response->withJson(['items' => $items], 200);
    }

    public function deleteDataset($slimRequest, $response, $args)
    {
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        $service = $this->getDataverseService($this->getRequest());

        $datasetDeleted = $service->deleteDraftDataset($study);

        if (!$datasetDeleted) {
            return $response->withStatus(500)->withJsonError('plugins.generic.dataverse.notification.statusInternalServerError');
        }

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function downloadDatasetFile($slimRequest, $response, $args)
    {
        $service = $this->getDataverseService($this->getRequest());
        $queryParams = $slimRequest->getQueryParams();
        $fileId = (int) $queryParams['fileId'];
        $filename = $queryParams['filename'];

        $dataverseResponse = $service->downloadDatasetFileById($fileId, $filename);

        if ($dataverseResponse['statusCode'] != 200) {
            return $response->withStatus($dataverseResponse['statusCode'])->withJsonError($dataverseResponse['message']);
        }

        return $response->withJson(['message' => 'ok'], 200);
    }

    private function getDataverseService($request): DataverseService
    {
        $contextId = $request->getContext()->getId();
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        import('plugins.generic.dataverse.classes.DataverseConfiguration');
        $configuration = new DataverseConfiguration(
            $plugin->getSetting($contextId, 'dataverseUrl'),
            $plugin->getSetting($contextId, 'apiToken')
        );
        import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');
        $serviceFactory = new DataverseServiceFactory();

        return $serviceFactory->build($configuration);
    }

    private function getDatasetFiles($study): array
    {
        $service = $this->getDataverseService($this->getRequest());
        $datasetFilesResponse = $service->getDatasetFiles($study);

        $datasetFiles = array();

        foreach ($datasetFilesResponse->data as $data) {
            $datasetFiles[] = ['id' => $data->dataFile->id, 'title' => $data->label];
        }

        return $datasetFiles;
    }

    private function registerDatasetEventLog(int $submissionId, int $eventType, string $message, array $params = [])
    {
        $request = Application::get()->getRequest();
        $submission = Services::get('submission')->get($submissionId);

        SubmissionLog::logEvent(
            $request,
            $submission,
            $eventType,
            $message,
            $params
        );
    }
}
