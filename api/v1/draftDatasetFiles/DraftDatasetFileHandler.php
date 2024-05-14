<?php

namespace APP\plugins\generic\dataverse\api\v1\draftDatasetFiles;

use PKP\handler\APIHandler;
use PKP\core\Core;
use APP\core\Application;
use APP\core\Services;
use PKP\file\TemporaryFileManager;
use PKP\log\event\SubmissionFileEventLogEntry;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DraftDatasetFileHandler extends APIHandler
{
    public $schemaName = 'draftDatasetFile';

    public function __construct()
    {
        $this->_handlerPath = 'draftDatasetFiles';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{fileId:\d+}',
                    'handler' => [$this, 'get'],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{fileId:\d+}/download',
                    'handler' => [$this, 'download'],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                ]
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'delete'],
                ],
            ]
        ];
        parent::__construct();
    }

    public function getMany($slimRequest, $response, $args)
    {
        $queryParams = $slimRequest->getQueryParams();
        $submissionId = $queryParams['submissionId'] ?? null;

        $draftDatasetFileRepo = Repo::draftDatasetFile();

        $result = is_null($submissionId) ? $draftDatasetFileRepo->getAll() : $draftDatasetFileRepo->getBySubmissionId($submissionId);

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
        $draftDatasetFile = Repo::draftDatasetFile()->get($args['fileId']);

        if (!$draftDatasetFile) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        return $response->withJson($this->getFullProperties($draftDatasetFile), 200);
    }

    public function download($slimRequest, $response, $args)
    {
        $draftDatasetFile = Repo::draftDatasetFile()->get($args['fileId']);

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
        $queryParams = $slimRequest->getQueryParams();
        $requestParams = $slimRequest->getParsedBody();

        $fileId = $requestParams['datasetFile']['temporaryFileId'];

        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($fileId, $queryParams['userId']);

        $params['submissionId'] = $queryParams['submissionId'];
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
        $draftDatasetFile = Repo::draftDatasetFile()->get((int) $queryParams['fileId']);

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
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $draftDatasetFile->getSubmissionId(),
            'eventType' => SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
            'message' => __($messageKey, ['filename' => $draftDatasetFile->getData('fileName')]),
            'isTranslated' => true,
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
            ->url($request, Application::ROUTE_API, $request->getContext()->getPath(), "draftDatasetFiles/$datasetFileId/download");

        $objectProps['downloadUrl'] = $datasetFileDownloadUrl;

        return $objectProps;
    }
}
