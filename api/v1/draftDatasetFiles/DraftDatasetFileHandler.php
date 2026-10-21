<?php

namespace APP\plugins\generic\dataverse\api\v1\draftDatasetFiles;

use PKP\handler\APIHandler;
use PKP\core\Core;
use APP\core\Application;
use PKP\security\Validation;
use APP\core\Services;
use PKP\file\TemporaryFileManager;
use PKP\log\event\SubmissionFileEventLogEntry;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\internal\PublicationCanBeEditedPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class DraftDatasetFileHandler extends APIHandler
{
    public $schemaName = 'draftDatasetFile';

    private const PUBLICATION_WRITE_OPERATIONS = ['add', 'delete'];

    public function __construct()
    {
        $this->_handlerPath = 'draftDatasetFiles';
        $roles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{fileId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{fileId:\d+}/download',
                    'handler' => [$this, 'download'],
                    'roles' => $roles,
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => $roles,
                ]
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'delete'],
                    'roles' => $roles,
                ],
            ]
        ];
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        $operation = $this->getSlimRequest()->getAttribute('route')->getName();
        if (in_array($operation, self::PUBLICATION_WRITE_OPERATIONS)) {
            $this->addPolicy(new PublicationCanBeEditedPolicy($request, 'api.submissions.403.userCantEdit'));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    private function getSubmissionDraftDatasetFile($draftDatasetFileId)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $draftDatasetFile = Repo::draftDatasetFile()->get((int) $draftDatasetFileId);

        if (!$draftDatasetFile || $draftDatasetFile->getSubmissionId() != $submission->getId()) {
            return null;
        }

        return $draftDatasetFile;
    }

    public function getMany($slimRequest, $response, $args)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $result = Repo::draftDatasetFile()->getBySubmissionId($submission->getId());

        $items = [];
        foreach ($result as $draftDatasetFile) {
            $items[] = $this->getFullProperties($draftDatasetFile);
        }

        ksort($items);

        return $response->withJson([
            'items' => $items,
        ], 200);
    }

    public function get($slimRequest, $response, $args)
    {
        $draftDatasetFile = $this->getSubmissionDraftDatasetFile($args['fileId']);

        if (!$draftDatasetFile) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        return $response->withJson($this->getFullProperties($draftDatasetFile), 200);
    }

    public function download($slimRequest, $response, $args)
    {
        $draftDatasetFile = $this->getSubmissionDraftDatasetFile($args['fileId']);

        if (!$draftDatasetFile) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($draftDatasetFile->getFileId(), $draftDatasetFile->getUserId());

        if (!$file) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $filePath = $temporaryFileManager->getBasePath() . $file->getServerFileName();
        $fileType = $file->getData('filetype');
        $fileName = $draftDatasetFile->getData('fileName');
        $temporaryFileManager->downloadByPath($filePath, $fileType, false, $fileName);

        return $response->withStatus(200);
    }

    public function add($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $user = Application::get()->getRequest()->getUser();

        $fileId = (int) ($requestParams['datasetFile']['temporaryFileId'] ?? 0);

        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($fileId, $user->getId());

        if (!$file) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params['submissionId'] = $submission->getId();
        $params['userId'] = $file->getUserId();
        $params['fileId'] = $file->getId();
        $params['fileName'] = $file->getOriginalFileName();
        $params = $this->convertStringsToSchema($this->schemaName, $params);

        $draftDatasetFile = Repo::draftDatasetFile()->newDataObject();
        $draftDatasetFile->setAllData($params);
        $draftDatasetFile->setId(Repo::draftDatasetFile()->add($draftDatasetFile));

        $this->createFileEventLog($draftDatasetFile, 'plugins.generic.dataverse.log.researchDataFileAdded');
        $draftDatasetFileProps = $this->getFullProperties($draftDatasetFile);

        return $response->withJson($draftDatasetFileProps, 200);
    }

    public function delete($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();
        $draftDatasetFile = $this->getSubmissionDraftDatasetFile($queryParams['fileId'] ?? 0);

        if (!$draftDatasetFile) {
            return $response->withStatus(404)->withJsonError('api.draftDatasetFile.404.drafDatasetFileNotFound');
        }

        $draftDatasetFileProps = $this->getFullProperties($draftDatasetFile);
        Repo::draftDatasetFile()->delete($draftDatasetFile);

        $this->createFileEventLog($draftDatasetFile, 'plugins.generic.dataverse.log.researchDataFileDeleted');

        return $response->withJson($draftDatasetFileProps, 200);
    }

    private function createFileEventLog($draftDatasetFile, $messageKey)
    {
        $user = Application::get()->getRequest()->getUser();

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $draftDatasetFile->getSubmissionId(),
            'userId' => Validation::loggedInAs() ?? $user->getId(),
            'eventType' => SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
            'message' => $messageKey,
            'isTranslated' => false,
            'filename' => $draftDatasetFile->getData('fileName'),
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    private function getFullProperties($object)
    {
        $props = Services::get('schema')->getFullProps($this->schemaName);

        $objectProps = [];
        foreach ($props as $prop) {
            $objectProps[$prop] = $object->getData($prop);
        }

        $request = Application::get()->getRequest();
        $datasetFileId = $object->getId();
        $datasetFileDownloadUrl = $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                "draftDatasetFiles/$datasetFileId/download",
                null,
                null,
                ['submissionId' => $object->getSubmissionId()]
            );

        $objectProps['downloadUrl'] = $datasetFileDownloadUrl;

        return $objectProps;
    }
}
