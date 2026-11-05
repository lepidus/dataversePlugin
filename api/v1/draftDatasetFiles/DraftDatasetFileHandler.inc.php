<?php

import('lib.pkp.classes.handler.APIHandler');

class DraftDatasetFileHandler extends APIHandler
{
    public $schemaName = 'draftDatasetFile';

    private const PUBLICATION_WRITE_OPERATIONS = ['add', 'delete'];

    public function __construct()
    {
        $this->_handlerPath = 'draftDatasetFiles';
        $roles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR];
        $this->_endpoints = array(
            'GET' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'getMany'),
                    'roles' => $roles,
                ),
            ),
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'add'),
                    'roles' => $roles,
                )
            ),
            'DELETE' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'delete'),
                    'roles' => $roles,
                ),
            )
        );
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        $operation = $this->getSlimRequest()->getAttribute('route')->getName();
        if (in_array($operation, self::PUBLICATION_WRITE_OPERATIONS)) {
            import('lib.pkp.classes.security.authorization.internal.PublicationCanBeEditedPolicy');
            $this->addPolicy(new PublicationCanBeEditedPolicy($request, 'api.submissions.403.userCantEdit'));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    private function getAuthorizedSubmission(): Submission
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    public function getMany($slimRequest, $response, $args)
    {
        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');

        $result = $draftDatasetFileDAO->getBySubmissionId($this->getAuthorizedSubmission()->getId());

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
        $requestParams = $slimRequest->getParsedBody();
        $user = Application::get()->getRequest()->getUser();

        $fileId = (int) ($requestParams['datasetFile']['temporaryFileId'] ?? 0);

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($fileId, $user->getId());

        if (!$file) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params['submissionId'] = $this->getAuthorizedSubmission()->getId();
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

        $draftDatasetFile = $draftDatasetFileDAO->getById((int) ($requestParams['fileId'] ?? 0));

        if (!$draftDatasetFile || $draftDatasetFile->getSubmissionId() != $this->getAuthorizedSubmission()->getId()) {
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
