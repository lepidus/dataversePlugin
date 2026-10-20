<?php

namespace APP\plugins\generic\dataverse\api\v1\datasets;

use APP\core\Application;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
use APP\plugins\generic\dataverse\classes\DraftDatasetFilesValidator;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\exception\MissingDepositFileException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\services\{
    DatasetFileService,
    DatasetService,
    DataverseService
};
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\TemporaryFileManager;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\internal\PublicationCanBeEditedPolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\Validation;

class DatasetHandler extends APIHandler
{
    private const MANAGER_ROLES = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    private const PUBLICATION_WRITE_OPERATIONS = ['addDataset', 'addFile', 'edit', 'deleteFile', 'deleteDataset'];

    private const EDITORIAL_DECISION_OPERATIONS = ['associateDataset', 'disassociateDataset', 'publishDataset'];

    private const BASE_METADATA_FIELDS = [
        'datasetTitle',
        'datasetDescription',
        'datasetKeywords',
        'datasetLanguage',
        'datasetSubject',
        'datasetLicense',
        'datasetRelationType',
    ];

    public function __construct()
    {
        $this->_handlerPath = 'datasets';
        $roles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];

        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/files',
                    'handler' => [$this, 'getFiles'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => [$this, 'downloadDatasetFile'],
                    'roles' => [...$roles, Role::ROLE_ID_REVIEWER]
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/citation',
                    'handler' => [$this, 'getCitation'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/inReview',
                    'handler' => [$this, 'getInReview'],
                    'roles' => $roles
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'addDataset'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/associate',
                    'handler' => [$this, 'associateDataset'],
                    'roles' => self::MANAGER_ROLES
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => [$this, 'addFile'],
                    'roles' => $roles
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => [$this, 'edit'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/disassociate',
                    'handler' => [$this, 'disassociateDataset'],
                    'roles' => self::MANAGER_ROLES
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/publish',
                    'handler' => [$this, 'publishDataset'],
                    'roles' => self::MANAGER_ROLES
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => [$this, 'deleteFile'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => [$this, 'deleteDataset'],
                    'roles' => $roles
                ]
            ]
        ];
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $operation = $this->getSlimRequest()->getAttribute('route')->getName();
        $permitDeclinedReviewer = $operation === 'downloadDatasetFile'
            && !$request->getContext()->getData('restrictReviewerFileAccess');

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', $permitDeclinedReviewer));

        if (in_array($operation, self::PUBLICATION_WRITE_OPERATIONS)) {
            $this->addPolicy(new PublicationCanBeEditedPolicy($request, 'api.submissions.403.userCantEdit'));
        }

        if (in_array($operation, self::EDITORIAL_DECISION_OPERATIONS)) {
            $this->addPolicy(new StageRolePolicy(self::MANAGER_ROLES, null, false));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    private function getSubmissionStudy(array $args): ?DataverseStudy
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $study = Repo::dataverseStudy()->get((int) $args['studyId']);

        if (!$study || $study->getSubmissionId() != $submission->getId()) {
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

    private function isFileAvailableToUser(int $fileId): bool
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        if (
            !$reviewAssignment
            || array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles)
            || $this->isAssignedToSubmission()
        ) {
            return true;
        }

        $context = Application::get()->getRequest()->getContext();
        if ($context->getData('restrictReviewerFileAccess') && !$reviewAssignment->getDateConfirmed()) {
            return false;
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        return in_array($fileId, (array) $submission->getData('selectedDataFilesForReview'));
    }

    protected function isAssignedToSubmission(): bool
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $user = Application::get()->getRequest()->getUser();

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        return (bool) $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $user->getId())->next();
    }

    private function createMetadataForm(): DatasetMetadataForm
    {
        (new DataverseClient())->getDataverseCollectionActions()->getRequiredMetadata();

        return new DatasetMetadataForm('', 'POST', null, 'workflow');
    }

    private function getAdditionalMetadata(array $params, DatasetMetadataForm $metadataForm): array
    {
        $additionalMetadata = [];

        foreach ($metadataForm->fields as $field) {
            if (in_array($field->name, self::BASE_METADATA_FIELDS) || !array_key_exists($field->name, $params)) {
                continue;
            }

            $metadataName = substr($field->name, strlen('dataset'));
            $metadataName = $metadataName === strtoupper($metadataName) ? $metadataName : lcfirst($metadataName);
            $additionalMetadata[$metadataName] = $params[$field->name];
        }

        return $additionalMetadata;
    }

    private function dataverseError($response, DataverseException $e)
    {
        error_log('Dataverse API error: ' . $e->getMessage());

        return $response
            ->withStatus($e->getCode())
            ->withJsonError($e->getUserMessageKey());
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
                Repo::dataverseStudy()->delete($study);

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
        $locale = Locale::getLocale();
        $study = $this->getSubmissionStudy($args);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $metadataForm = $this->createMetadataForm();
        } catch (DataverseException $e) {
            return $this->dataverseError($response, $e);
        }

        $data = [];
        $data['persistentId'] = $study->getPersistentId();
        $data['title'] = $requestParams['datasetTitle'];
        $data['description'] = $requestParams['datasetDescription'];
        $data['keywords'] = $requestParams['datasetKeywords'][$locale];
        $data['language'] = $requestParams['datasetLanguage'];
        $data['subject'] = $requestParams['datasetSubject'];
        $data['license'] = $requestParams['datasetLicense'];
        $data['relationType'] = $requestParams['datasetRelationType'];

        $datasetService = new DatasetService();
        $datasetService->update(array_merge($data, $this->getAdditionalMetadata($requestParams, $metadataForm)));

        return $this->get($slimRequest, $response, $args);
    }

    public function associateDataset($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $persistentId = $requestParams['datasetPersistentId'];

        $datasetService = new DatasetService();
        $associationResult = $datasetService->associate($submission->getId(), $persistentId);

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
            $error = $e->getMessage();
            $message = 'plugins.generic.dataverse.error.publishFailed';

            error_log('Dataverse API error: ' . $error);

            $this->createEventLog($study, $message, ['dataverseError' => $error]);

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
        $locale = Locale::getLocale();

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionId = $submission->getId();

        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submissionId)->toArray();

        if (empty($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.researchDataFile.error');
        }

        $datasetFilesValidator = new DraftDatasetFilesValidator();
        if (!$datasetFilesValidator->datasetHasReadmeFile($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.error.readmeFile.required');
        }

        if (!$datasetFilesValidator->datasetHasNonReadmeFile($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.error.notSolelyReadmeFile');
        }

        try {
            $metadataForm = $this->createMetadataForm();
        } catch (DataverseException $e) {
            return $this->dataverseError($response, $e);
        }

        $datasetFactory = new SubmissionDatasetFactory($submission);
        try {
            $dataset = $datasetFactory->getDataset();
        } catch (MissingDepositFileException $e) {
            return $response->withStatus(409)->withJsonError('plugins.generic.dataverse.error.sourceFileMissing');
        }
        $dataset->setTitle($requestParams['datasetTitle']);
        $dataset->setDescription($requestParams['datasetDescription']);
        $dataset->setKeywords((array) $requestParams['datasetKeywords'][$locale]);
        $dataset->setLanguage($requestParams['datasetLanguage']);
        $dataset->setSubject($requestParams['datasetSubject']);
        $dataset->setLicense($requestParams['datasetLicense']);

        $relatedPublication = $dataset->getRelatedPublication();
        $relatedPublication->setData('RelationType', $requestParams['datasetRelationType']);
        $dataset->setRelatedPublication($relatedPublication);

        foreach ($this->getAdditionalMetadata($requestParams, $metadataForm) as $metadataName => $value) {
            $dataset->setData($metadataName, $value);
        }

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
        $request = Application::get()->getRequest();

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

        $items = array_map(function ($datasetFile) use ($request, $study) {
            $fileVars = $datasetFile->getVars();
            $fileVars['downloadUrl'] = $request->getDispatcher()->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'datasets/' . $study->getId() . '/file',
                null,
                null,
                ['submissionId' => $study->getSubmissionId(), 'fileId' => $datasetFile->getId()]
            );
            return $fileVars;
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

        $queryParams = $slimRequest->getQueryParams();
        $datasetIsPublished = (bool) $queryParams['datasetIsPublished'];

        try {
            $dataverseClient = new DataverseClient();
            $citationData = $dataverseClient->getDatasetActions()->getCitation($study->getPersistentId(), $datasetIsPublished);
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
        $queryParams = $slimRequest->getQueryParams();
        $datasetId = $queryParams['datasetId'];
        $study = $this->getSubmissionStudy($args);
        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $dataverseClient = new DataverseClient();
            $datasetLocks = $dataverseClient->getDatasetActions()->getDatasetLocks($datasetId);
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
        $deleteMessage = null;

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $requestParams = $slimRequest->getParsedBody();
        if (isset($requestParams['sendDeleteEmail'])) {
            $sendDeleteEmail = (int) $requestParams['sendDeleteEmail'];
            if ($sendDeleteEmail == 1 && isset($requestParams['deleteMessage'])) {
                $deleteMessage = $requestParams['deleteMessage'];
            }
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

    private function createEventLog($study, $messageKey, $params)
    {
        $user = Application::get()->getRequest()->getUser();

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $study->getSubmissionId(),
            'userId' => Validation::loggedInAs() ?? $user->getId(),
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'message' => $messageKey,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            ...$params
        ]);
        Repo::eventLog()->add($eventLog);
    }
}
