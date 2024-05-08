<?php

namespace APP\plugins\generic\dataverse\api\v1\draftDatasetFiles;

use PKP\handler\APIHandler;
use APP\core\Services;
use PKP\file\TemporaryFileManager;
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
        $requestParams = $slimRequest->getQueryParams();
        $submissionId = $requestParams['submissionId'] ?? null;

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

        $draftDatasetFileProps = $this->getFullProperties($draftDatasetFile);

        return $response->withJson($draftDatasetFileProps, 200);
    }

    public function delete($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getQueryParams();
        $draftDatasetFile = Repo::draftDatasetFile()->get((int) $requestParams['fileId']);

        if (!$draftDatasetFile) {
            return $response->withStatus(404)->withJsonError('api.draftDatasetFile.404.drafDatasetFileNotFound');
        }

        $draftDatasetFileProps = $this->getFullProperties($draftDatasetFile);
        Repo::draftDatasetFile()->delete($draftDatasetFile);

        return $response->withJson($draftDatasetFileProps, 200);
    }

    private function getFullProperties($object)
    {
        $props = Services::get('schema')->getFullProps($this->schemaName);

        $objectProps = [];
        foreach ($props as $prop) {
            $objectProps[$prop] = $object->getData($prop);
        }

        return $objectProps;
    }
}
