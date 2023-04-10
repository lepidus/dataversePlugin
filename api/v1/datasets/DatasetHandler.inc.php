<?php

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.log.SubmissionLog');
import('classes.log.SubmissionEventLogEntry');
import('plugins.generic.dataverse.classes.services.DatasetService');
import('plugins.generic.dataverse.classes.services.DatasetFileService');

class DatasetHandler extends APIHandler
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
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/citation',
                    'handler' => array($this, 'getCitation'),
                    'roles' => $roles
                ),
            ),
            'DELETE' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/files',
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
        $requestParams = $slimRequest->getParsedBody();
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudy($args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $data = [];
        $data['persistentId'] = $study->getPersistentId();
        $data['title'] = $requestParams['datasetTitle'];
        $data['description'] = $requestParams['datasetDescription'];
        $data['keywords'] = (array) $requestParams['datasetKeywords'];
        $data['subject'] = $requestParams['datasetSubject'];

        $datasetService = new DatasetService();
        $datasetService->update($data);

        return $response->withJson(['message' => 'ok'], 200);
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
        import('plugins.generic.dataverse.classes.factories.SubmissionDatasetFactory');
        $datasetFactory = new SubmissionDatasetFactory($submission);
        $dataset = $datasetFactory->getDataset();
        $dataset->setTitle($requestParams['datasetTitle']);
        $dataset->setDescription($requestParams['datasetDescription']);
        $dataset->setKeywords((array) $requestParams['datasetKeywords']);
        $dataset->setSubject($requestParams['datasetSubject']);

        import('plugins.generic.dataverse.classes.dataverseAPI.packagers.NativeAPIDatasetPackager');
        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->createDatasetPackage();
        $datasetPackagePath = $packager->getPackagePath();

        $dataverseConfig = DAORegistry::getDAO('DataverseConfigurationDAO')->get($submission->getContextId());

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

        DAORegistry::getDAO('DraftDatasetFileDAO')->deleteBySubmissionId($submissionId);

        $this->registerDatasetEventLog(
            $submissionId,
            SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'plugins.generic.dataverse.log.researchDataDeposited',
            ['persistentURL' => $study->getPersistentUri()]
        );

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function addFile($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $fileId = $requestParams['datasetFile']['temporaryFileId'];

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        $datasetFileService = new DatasetFileService();
        $datasetFileService->add($study, $fileId);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function getFiles($slimRequest, $response, $args)
    {
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        $dataverseClient = new DataverseClient();
        $datasetFiles = $dataverseClient->getDatasetFileActions()->getByDatasetId($study->getPersistentId());

        $items = array_map(function (DatasetFile $file) {
            return $file->getVars();
        }, $datasetFiles);

        ksort($items);

        return $response->withJson(['items' => $items], 200);
    }

    public function getCitation($slimRequest, $response, $args)
    {
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $dataverseClient = new DataverseClient();
        $citation = $dataverseClient->getDatasetActions()->getCitation($study->getPersistentId());

        return $response->withJson(['citation' => $citation], 200);
    }

    public function deleteFile($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $datasetFileService = new DatasetFileService();
        $datasetFileService->delete($study, $queryParams['fileId'], $queryParams['filename']);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function deleteDataset($slimRequest, $response, $args)
    {
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $datasetService = new DatasetService();
        $datasetService->delete($study);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function downloadDatasetFile($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();
        $fileId = (int) $queryParams['fileId'];
        $filename = $queryParams['filename'];

        $dataverseClient = new DataverseClient();
        $dataverseClient->getDatasetFileActions()->download($fileId, $filename);

        return $response->withJson(['message' => 'ok'], 200);
    }
}
