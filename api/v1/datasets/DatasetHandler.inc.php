<?php

import('plugins.generic.dataverse.classes.exception.DataverseException');
import('plugins.generic.dataverse.classes.exception.MissingDepositFileException');

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.log.SubmissionLog');
import('classes.log.SubmissionEventLogEntry');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.classes.services.DatasetService');
import('plugins.generic.dataverse.classes.services.DatasetFileService');
import('plugins.generic.dataverse.classes.services.DataverseService');

class DatasetHandler extends APIHandler
{
    private const MANAGER_ROLES = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR];

    private const PUBLICATION_WRITE_OPERATIONS = ['addDataset', 'addFile', 'edit', 'deleteFile', 'deleteDataset'];

    private const EDITORIAL_DECISION_OPERATIONS = ['associateDataset', 'disassociateDataset', 'publishDataset'];

    public function __construct()
    {
        $this->_handlerPath = 'datasets';
        $roles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR];

        $this->_endpoints = array(
            'GET' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => array($this, 'get'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/files',
                    'handler' => array($this, 'getFiles'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => array($this, 'downloadDatasetFile'),
                    'roles' => array_merge($roles, [ROLE_ID_REVIEWER])
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/citation',
                    'handler' => array($this, 'getCitation'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/inReview',
                    'handler' => array($this, 'getInReview'),
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
                    'pattern' => $this->getEndpointPattern() . '/associate',
                    'handler' => [$this, 'associateDataset'],
                    'roles' => self::MANAGER_ROLES
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => array($this, 'addFile'),
                    'roles' => $roles
                ),
            ),
            'PUT' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => array($this, 'edit'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/disassociate',
                    'handler' => [$this, 'disassociateDataset'],
                    'roles' => self::MANAGER_ROLES
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/publish',
                    'handler' => array($this, 'publishDataset'),
                    'roles' => self::MANAGER_ROLES
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
        $operation = $this->getSlimRequest()->getAttribute('route')->getName();
        $permitDeclinedReviewer = $operation === 'downloadDatasetFile'
            && !$request->getContext()->getData('restrictReviewerFileAccess');

        import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', $permitDeclinedReviewer));

        if (in_array($operation, self::PUBLICATION_WRITE_OPERATIONS)) {
            import('lib.pkp.classes.security.authorization.internal.PublicationCanBeEditedPolicy');
            $this->addPolicy(new PublicationCanBeEditedPolicy($request, 'api.submissions.403.userCantEdit'));
        }

        if (in_array($operation, self::EDITORIAL_DECISION_OPERATIONS)) {
            import('lib.pkp.classes.security.authorization.StageRolePolicy');
            $this->addPolicy(new StageRolePolicy(self::MANAGER_ROLES, null, false));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    private function getAuthorizedSubmission(): Submission
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    private function getSubmissionStudy(array $args): ?DataverseStudy
    {
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudy((int) $args['studyId']);

        if (!$study || $study->getSubmissionId() != $this->getAuthorizedSubmission()->getId()) {
            return null;
        }

        return $study;
    }

    private function getStudyDatasetFile(DataverseStudy $study, int $fileId): ?DatasetFile
    {
        $datasetFiles = (new DataverseClient())
            ->getDatasetFileActions()
            ->getByDatasetId($study->getPersistentId());

        foreach ($datasetFiles as $datasetFile) {
            if ((int) $datasetFile->getId() === $fileId) {
                return $datasetFile;
            }
        }

        return null;
    }

    private function dataverseError($response, DataverseException $e)
    {
        error_log('Dataverse API error: ' . $e->getMessage());

        return $response
            ->withStatus($e->getCode())
            ->withJsonError($e->getUserMessageKey());
    }

    private function isFileAvailableToUser(int $fileId): bool
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $userRoles = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

        if (
            !$reviewAssignment
            || array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], $userRoles)
            || $this->isAssignedToSubmission()
        ) {
            return true;
        }

        $context = Application::get()->getRequest()->getContext();
        if ($context->getData('restrictReviewerFileAccess') && !$reviewAssignment->getDateConfirmed()) {
            return false;
        }

        return in_array($fileId, (array) $this->getAuthorizedSubmission()->getData('selectedDataFilesForReview'));
    }

    protected function isAssignedToSubmission(): bool
    {
        $user = Application::get()->getRequest()->getUser();
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

        return (bool) $stageAssignmentDao
            ->getBySubmissionAndUserIdAndStageId($this->getAuthorizedSubmission()->getId(), $user->getId())
            ->next();
    }

    private function mapServiceStatusToHttpCode(string $status): ?int
    {
        $map = [
            DataverseService::STATUS_SUCCESS => 200,
            DataverseService::STATUS_NOT_FOUND => 404,
            DataverseService::STATUS_ERROR => 403
        ];

        return $map[$status] ?? null;
    }

    public function get($slimRequest, $response, $args)
    {
        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
        } catch (DataverseException $e) {
            if ($e->getCode() === 404) {
                DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);

                return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
            }

            error_log('Dataverse API error: ' . $e->getMessage());

            return $response
                ->withStatus($e->getCode())
                ->withJsonError($e->getUserMessageKey());
        }

        return $response->withJson($dataset->getAllData(), 200);
    }

    public function edit($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $data = [];
        $data['persistentId'] = $study->getPersistentId();
        $data['title'] = $requestParams['datasetTitle'];
        $data['description'] = $requestParams['datasetDescription'];
        $data['keywords'] = (array) $requestParams['datasetKeywords'];
        $data['language'] = $requestParams['datasetLanguage'];
        $data['subject'] = $requestParams['datasetSubject'];
        $data['license'] = $requestParams['datasetLicense'];
        $data['relationType'] = $requestParams['datasetRelationType'];

        $datasetService = new DatasetService();
        $datasetService->update($data);

        return $this->get($slimRequest, $response, $args);
    }

    public function associateDataset($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $persistentId = $requestParams['datasetPersistentId'];

        $datasetService = new DatasetService();
        $associationResult = $datasetService->associate($this->getAuthorizedSubmission()->getId(), $persistentId);

        if ($associationResult['status'] !== DataverseService::STATUS_SUCCESS) {
            return $response
                ->withStatus($this->mapServiceStatusToHttpCode($associationResult['status']))
                ->withJsonError($associationResult['message']);
        }

        return $response->withStatus(200);
    }

    public function disassociateDataset($slimRequest, $response, $args)
    {
        $study = $this->getSubmissionStudy($args);
        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $datasetService = new DatasetService();
        $datasetService->disassociate($study);

        return $response->withStatus(200);
    }

    public function publishDataset($slimRequest, $response, $args)
    {
        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $dataverseClient = new DataverseClient();
        try {
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            if ($dataset->isPublished()) {
                return $response->withStatus(403)->withJsonError('api.dataset.403.alreadyPublished');
            }

            $dataverseClient->getDatasetActions()->publish($study->getPersistentId());
            $dataset->setVersionState(Dataset::VERSION_STATE_RELEASED);
        } catch (DataverseException $e) {
            $request = $this->getRequest();
            $submission = Services::get('submission')->get($study->getSubmissionId());
            $error = $e->getMessage();
            $message = 'plugins.generic.dataverse.error.publishFailed';

            error_log('Dataverse API error: ' . $error);

            SubmissionLog::logEvent(
                $request,
                $submission,
                SUBMISSION_LOG_METADATA_UPDATE,
                $message,
                ['error' => $error]
            );
            return $response->withStatus(403)->withJsonError(
                $message,
                ['error' => $error]
            );
        }

        return $response->withJson(
            $dataset->getAllData(),
            200
        );
    }

    public function addDataset($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $submission = $this->getAuthorizedSubmission();
        $submissionId = $submission->getId();

        $draftDatasetFiles = DAORegistry::getDAO('DraftDatasetFileDAO')->getBySubmissionId($submissionId);

        if (empty($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.researchDataFile.error');
        }

        import('plugins.generic.dataverse.classes.DraftDatasetFilesValidator');
        $datasetFilesValidator = new DraftDatasetFilesValidator();
        if (!$datasetFilesValidator->datasetHasReadmeFile($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.error.readmeFileRequired');
        }

        if (!$datasetFilesValidator->datasetHasNonReadmeFile($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.error.notSolelyReadmeFile');
        }

        import('plugins.generic.dataverse.classes.factories.SubmissionDatasetFactory');
        $datasetFactory = new SubmissionDatasetFactory($submission);
        try {
            $dataset = $datasetFactory->getDataset();
        } catch (MissingDepositFileException $e) {
            return $response->withStatus(409)->withJsonError('plugins.generic.dataverse.error.sourceFileMissing');
        }
        if (empty($dataset->getFiles())) {
            return $response->withStatus(409)->withJsonError('plugins.generic.dataverse.error.sourceFileMissing');
        }
        $dataset->setTitle($requestParams['datasetTitle']);
        $dataset->setDescription($requestParams['datasetDescription']);
        $dataset->setKeywords((array) $requestParams['datasetKeywords']);
        $dataset->setLanguage($requestParams['datasetLanguage']);
        $dataset->setSubject($requestParams['datasetSubject']);
        $dataset->setLicense($requestParams['datasetLicense']);

        $relatedPublication = $dataset->getRelatedPublication();
        $relatedPublication->setData('RelationType', $requestParams['datasetRelationType']);
        $dataset->setRelatedPublication($relatedPublication);

        if (!empty($dataset->getFiles())) {
            $datasetService = new DatasetService();
            $depositInfo = $datasetService->deposit($submission, $dataset);
            if ($depositInfo['status'] != 'Success') {
                return $response->withStatus(403)->withJsonError(
                    $depositInfo['message'] . '.author',
                    $depositInfo['messageParams']
                );
            }
        }

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function addFile($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $fileId = (int) ($requestParams['datasetFile']['temporaryFileId'] ?? 0);

        $study = $this->getSubmissionStudy($args);
        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $user = Application::get()->getRequest()->getUser();
        import('lib.pkp.classes.file.TemporaryFileManager');
        if (!(new TemporaryFileManager())->getFile($fileId, $user->getId())) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $datasetFileService = new DatasetFileService();
        $datasetFileService->add($study, $fileId);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function getFiles($slimRequest, $response, $args)
    {
        $study = $this->getSubmissionStudy($args);
        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $dataverseClient = new DataverseClient();
            $datasetFiles = $dataverseClient->getDatasetFileActions()->getByDatasetId($study->getPersistentId());
        } catch (DataverseException $e) {
            error_log('Error getting dataset files: ' . $e->getMessage());
            return $response
                ->withStatus($e->getCode())
                ->withJsonError($e->getUserMessageKey());
        }

        $items = array_map(function (DatasetFile $file) {
            return $file->getVars();
        }, $datasetFiles);

        ksort($items);

        return $response->withJson(['items' => $items], 200);
    }

    public function getCitation($slimRequest, $response, $args)
    {
        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $dataverseClient = new DataverseClient();
            $citationData = $dataverseClient->getDatasetActions()->getCitation($study->getPersistentId());
        } catch (DataverseException $e) {
            error_log('Error getting citation: ' . $e->getMessage());
            return $response
                ->withStatus($e->getCode())
                ->withJsonError('api.error.researchDataCitationNotFound');
        }

        return $response->withJson(['citation' => $citationData['citation']], 200);
    }

    public function getInReview($slimRequest, $response, $args)
    {
        $study = $this->getSubmissionStudy($args);
        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $dataverseClient = new DataverseClient();
            $datasetLocks = $dataverseClient->getDatasetActions()->getDatasetLocks($study->getPersistentId());
        } catch (DataverseException $e) {
            error_log('Error getting dataset locks: ' . $e->getMessage());
            return $response
                ->withStatus($e->getCode())
                ->withJsonError($e->getUserMessageKey());
        }

        foreach ($datasetLocks as $lock) {
            if ($lock['lockType'] == 'InReview') {
                return $response->withJson(['inReview' => true], 200);
            }
        }

        return $response->withJson(['inReview' => false], 200);
    }

    public function deleteFile($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();

        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $datasetFile = $this->getStudyDatasetFile($study, (int) ($queryParams['fileId'] ?? 0));
        } catch (DataverseException $e) {
            return $this->dataverseError($response, $e);
        }

        if (!$datasetFile) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $datasetFileService = new DatasetFileService();
        $datasetFileService->delete($study, (string) $datasetFile->getId(), $datasetFile->getFileName());

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function deleteDataset($slimRequest, $response, $args)
    {
        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $deleteMessage = null;
        $requestParams = $slimRequest->getParsedBody();
        $sendDeleteEmail = (int) $requestParams['sendDeleteEmail'];
        if ($sendDeleteEmail == 1 && isset($requestParams['deleteMessage'])) {
            $deleteMessage = $requestParams['deleteMessage'];
        }

        $datasetService = new DatasetService();
        $datasetService->delete($study, $deleteMessage);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function downloadDatasetFile($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();
        $fileId = (int) ($queryParams['fileId'] ?? 0);
        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if (!$this->isFileAvailableToUser($fileId)) {
            return $response->withStatus(403)->withJsonError('api.403.unauthorized');
        }

        try {
            $datasetFile = $this->getStudyDatasetFile($study, $fileId);
        } catch (DataverseException $e) {
            return $this->dataverseError($response, $e);
        }

        if (!$datasetFile) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        (new DataverseClient())->getDatasetFileActions()->download($datasetFile->getId(), $datasetFile->getFileName());

        return $response->withStatus(200);
    }
}
