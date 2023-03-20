<?php

import('lib.pkp.classes.handler.APIHandler');

class DraftDatasetFileHandler extends APIHandler
{
    public $schemaName = 'draftDatasetFile';

    public function __construct()
    {
        $this->_handlerPath = 'draftDatasetFiles';
        $this->_endpoints = array(
            'GET' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'getMany'),
                ),
            ),
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'add'),
                )
            ),
            'DELETE' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'delete'),
                ),
            )
        );
        parent::__construct();
    }

    public function getMany($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getQueryParams();
        $submissionId = $requestParams['submissionId'] ?? null;

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');

        $result = is_null($submissionId) ? $draftDatasetFileDAO->getAll() : $draftDatasetFileDAO->getBySubmissionId($submissionId);

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

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($fileId, $queryParams['userId']);

        $params['submissionId'] = $queryParams['submissionId'];
        $params['userId'] = $file->getUserId();
        $params['fileId'] = $file->getId();
        $params['fileName'] = $file->getOriginalFileName();
        $params = $this->convertStringsToSchema($this->schemaName, $params);

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFile = $draftDatasetFileDAO->newDataObject();
        $draftDatasetFile->setAllData($params);
        $draftDatasetFile->setId($draftDatasetFileDAO->insertObject($draftDatasetFile));

        $draftDatasetFileProps = $this->getFullProperties($draftDatasetFile);

        return $response->withJson($draftDatasetFileProps, 200);
    }

    public function delete($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getQueryParams();
        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');

        $draftDatasetFile = $draftDatasetFileDAO->getById((int) $requestParams['fileId']);

        if (!$draftDatasetFile) {
            return $response->withStatus(404)->withJsonError('api.draftDatasetFile.404.drafDatasetFileNotFound');
        }

        $draftDatasetFileProps = $this->getFullProperties($draftDatasetFile);

        $draftDatasetFileDAO->deleteObject($draftDatasetFile);

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
