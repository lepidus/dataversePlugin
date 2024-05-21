<?php

namespace APP\plugins\generic\dataverse\api\v1\draftDatasetFiles;

use PKP\handler\APIHandler;
use PKP\security\Role;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
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
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/citation',
                    'handler' => [$this, 'getCitation'],
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
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/files',
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
            $request = $this->getRequest();
            $submission = Repo::submission()->get($study->getSubmissionId());
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
        $study = Repo::dataverseStudy()->get($args['studyId']);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $data = [];
        $data['persistentId'] = $study->getPersistentId();
        $data['title'] = $requestParams['datasetTitle'];
        $data['description'] = $requestParams['datasetDescription'];
        $data['keywords'] = (array) $requestParams['datasetKeywords'];
        $data['subject'] = $requestParams['datasetSubject'];
        $data['license'] = $requestParams['datasetLicense'];

        $datasetService = new DatasetService();
        $datasetService->update($data);

        return $this->get($slimRequest, $response, $args);
    }

    public function publishDataset($slimRequest, $response, $args)
    {
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudy($args['studyId']);

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
        $dataset->setLicense($requestParams['datasetLicense']);

        if (!empty($dataset->getFiles())) {
            try {
                $datasetService = new DatasetService();
                $datasetService->deposit($submission, $dataset);
            } catch (DataverseException $e) {
                return $response->withStatus(403)
                    ->withJsonError(
                        'plugins.generic.dataverse.error.depositFailed',
                        ['error' => $e->getMessage()]
                    );
            }
        }

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

        try {
            $dataverseClient = new DataverseClient();
            $datasetFiles = $dataverseClient->getDatasetFileActions()->getByDatasetId($study->getPersistentId());
        } catch (DataverseException $e) {
            error_log('Error getting dataset files: ' . $e->getMessage());
            return $response->withStatus($e->getCode())->withJson(['error' => $e->getMessage()]);
        }

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

        try {
            $dataverseClient = new DataverseClient();
            $citation = $dataverseClient->getDatasetActions()->getCitation($study->getPersistentId());
        } catch (DataverseException $e) {
            error_log('Error getting citation: ' . $e->getMessage());
            return $response->withStatus($e->getCode())->withJsonError('api.error.researchDataCitationNotFound');
        }

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
        $deleteMessage = null;

        $requestParams = $slimRequest->getParsedBody();
        if (isset($requestParams['deleteMessage'])) {
            $deleteMessage = $requestParams['deleteMessage'];
        }

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $datasetService = new DatasetService();
        $datasetService->delete($study, $deleteMessage);

        return $response->withJson(['message' => 'ok'], 200);
    }

    public function downloadDatasetFile($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();
        $fileId = (int) $queryParams['fileId'];
        $filename = $queryParams['filename'];

        $dataverseClient = new DataverseClient();
        $dataverseClient->getDatasetFileActions()->download($fileId, $filename);

        return $response->withStatus(200);
    }
}
