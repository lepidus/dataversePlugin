<?php

namespace APP\plugins\generic\dataverse\api\v1\datasets;

use PKP\handler\APIHandler;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\facades\Locale;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\db\DAORegistry;
use APP\core\Application;
use APP\log\event\SubmissionEventLogEntry;
use PKP\core\Core;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\classes\services\DatasetFileService;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\DraftDatasetFilesValidator;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DatasetHandler extends APIHandler
{
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
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/publish',
                    'handler' => [$this, 'publishDataset'],
                    'roles' => $roles
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
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function get($slimRequest, $response, $args)
    {
        $study = Repo::dataverseStudy()->get($args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
        } catch (DataverseException $e) {
            if ($e->getCode() === 404) {
                DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);
            }

            $error = $e->getMessage();
            $message = 'plugins.generic.dataverse.error.getFailed';

            error_log('Dataverse API error: ' . $error);

            return $response->withStatus(403)->withJsonError(
                $message,
                ['error' => $error]
            );
        }

        return $response->withJson($dataset->getAllData(), 200);
    }

    public function edit($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $locale = Locale::getLocale();
        $study = Repo::dataverseStudy()->get($args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $data = [];
        $data['persistentId'] = $study->getPersistentId();
        $data['title'] = $requestParams['datasetTitle'];
        $data['description'] = $requestParams['datasetDescription'];
        $data['keywords'] = $requestParams['datasetKeywords'][$locale];
        $data['subject'] = $requestParams['datasetSubject'];
        $data['license'] = $requestParams['datasetLicense'];

        $datasetService = new DatasetService();
        $datasetService->update($data);

        return $this->get($slimRequest, $response, $args);
    }

    public function publishDataset($slimRequest, $response, $args)
    {
        $study = Repo::dataverseStudy()->get($args['studyId']);

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
            $submission = Repo::submission()->get($study->getSubmissionId());
            $error = $e->getMessage();
            $message = 'plugins.generic.dataverse.error.publishFailed';

            error_log('Dataverse API error: ' . $error);

            $this->createEventLog($study, $message, ['error' => $error]);

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
        $queryParams = $slimRequest->getQueryParams();
        $locale = Locale::getLocale();

        $submissionId = $queryParams['submissionId'];
        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submissionId)->toArray();

        if (empty($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.researchDataFile.error');
        }

        $datasetFilesValidator = new DraftDatasetFilesValidator();
        if (!$datasetFilesValidator->datasetHasReadmeFile($draftDatasetFiles)) {
            return $response->withStatus(404)->withJsonError('plugins.generic.dataverse.error.readmeFile.required');
        }

        $submission = Repo::submission()->get($submissionId);

        $datasetFactory = new SubmissionDatasetFactory($submission);
        $dataset = $datasetFactory->getDataset();
        $dataset->setTitle($requestParams['datasetTitle']);
        $dataset->setDescription($requestParams['datasetDescription']);
        $dataset->setKeywords((array) $requestParams['datasetKeywords'][$locale]);
        $dataset->setSubject($requestParams['datasetSubject']);
        $dataset->setLicense($requestParams['datasetLicense']);

        if (!empty($dataset->getFiles())) {
            $datasetService = new DatasetService();
            $depositInfo = $datasetService->deposit($submission, $dataset);
            if ($depositInfo['status'] != 'Success') {
                return $response->withStatus(403)->withJsonError(
                    $depositInfo['message'].'.author',
                    $depositInfo['messageParams']
                );
            }
        }

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function addFile($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $fileId = $requestParams['datasetFile']['temporaryFileId'];

        $study = Repo::dataverseStudy()->get($args['studyId']);

        $datasetFileService = new DatasetFileService();
        $datasetFileService->add($study, $fileId);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function getFiles($slimRequest, $response, $args)
    {
        $study = Repo::dataverseStudy()->get($args['studyId']);
        $request = Application::get()->getRequest();

        try {
            $dataverseClient = new DataverseClient();
            $datasetFiles = $dataverseClient->getDatasetFileActions()->getByDatasetId($study->getPersistentId());
        } catch (DataverseException $e) {
            error_log('Error getting dataset files: ' . $e->getMessage());
            return $response->withStatus($e->getCode())->withJson(['error' => $e->getMessage()]);
        }

        $fileActionApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $request->getContext()->getPath(), 'datasets/' . $study->getId() . '/file');

        $items = array_map(function ($datasetFile) use ($fileActionApiUrl) {
            $fileVars = $datasetFile->getVars();
            $fileVars['downloadUrl'] = $fileActionApiUrl . '?fileId=' . $datasetFile->getId() . '&fileName=' . $datasetFile->getFileName();
            return $fileVars;
        }, $datasetFiles);

        ksort($items);

        return $response->withJson(['items' => $items], 200);
    }

    public function getCitation($slimRequest, $response, $args)
    {
        $study = Repo::dataverseStudy()->get((int) $args['studyId']);

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
            return $response->withStatus($e->getCode())->withJsonError('api.error.researchDataCitationNotFound');
        }

        return $response->withJson(['citation' => $citationData['citation']], 200);
    }

    public function getInReview($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();
        $datasetId = $queryParams['datasetId'];

        try {
            $dataverseClient = new DataverseClient();
            $datasetLocks = $dataverseClient->getDatasetActions()->getDatasetLocks($datasetId);
        } catch (DataverseException $e) {
            error_log('Error getting dataset locks: ' . $e->getMessage());
            return $response->withStatus($e->getCode());
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
        $study = Repo::dataverseStudy()->get($args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $datasetFileService = new DatasetFileService();
        $datasetFileService->delete($study, $queryParams['fileId'], $queryParams['fileName']);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function deleteDataset($slimRequest, $response, $args)
    {
        $study = Repo::dataverseStudy()->get($args['studyId']);
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
        $fileId = (int) $queryParams['fileId'];
        $filename = $queryParams['fileName'];

        $dataverseClient = new DataverseClient();
        $dataverseClient->getDatasetFileActions()->download($fileId, $filename);

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
            'message' => __($messageKey, $params),
            'isTranslated' => true,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }
}
