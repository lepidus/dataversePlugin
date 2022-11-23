<?php

import('lib.pkp.classes.handler.APIHandler');

class DatasetsHandler extends APIHandler
{
    public function __construct() {
		$this->_handlerPath = 'datasets';
        $this->_endpoints = array(
            'PUT' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}',
                    'handler' => array($this, 'edit'),
                    'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR]
                ),
            ),
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/file',
                    'handler' => array($this, 'addFile'),
                    'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR]
                ),
            ),
            'GET' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/{studyId}/files',
                    'handler' => array($this, 'getFiles'),
                    'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR]
                ),
            )
        );
        parent::__construct();
    }

    function authorize($request, &$args, $roleAssignments) {
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
        $studyId = $args['studyId'];

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy($studyId);

        if (!$study) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $requestParams = $slimRequest->getParsedBody();
        $requestParams['datasetDescription'] = [$requestParams['datasetDescription']];

        $service = $this->getDataverseService($this->getRequest());

        $datasetResponse = $service->getDatasetResponse($study);

        if (!empty($datasetResponse)) {
            $metadataBlocks = $datasetResponse->data->latestVersion->metadataBlocks;

            import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');
            $datasetDataCreator = new DataverseDatasetDataCreator();
            $datasetMetadata = $datasetDataCreator->updataMetadataBlocks($metadataBlocks, $requestParams);

            $jsonMetadata = json_encode($datasetMetadata);

            $dataverseResponse = $service->updateDatasetData($jsonMetadata, $study);

            return $response->withJson($dataverseResponse, 200);
        }
        else {
            return $response->withStatus(500)->withJsonError('plugins.generic.dataverse.notification.statusInternalServerError');
        }
    }

    public function addFile($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $request = $this->getRequest();
		$user = $request->getUser();

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);
        $fileId = $requestParams['datasetFile']['temporaryFileId'];

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($fileId, $user->getId());
        
        $service = $this->getDataverseService($request);
        $dataverseResponse = $service->addDatasetFile($study, $file);

        $datasetFileData = [
            'fileName' => $file->getOriginalFileName()
        ];

        $temporaryFileManager->deleteById($file->getId(), $user->getId());
        
        if (!$dataverseResponse) {
            return $response->withStatus(500)->withJsonError('plugins.generic.dataverse.notification.statusInternalServerError');
        }

        return $response->withJson($datasetFileData, 200);
    }

    public function getFiles($slimRequest, $response, $args)
    {
        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->getStudy((int) $args['studyId']);

        $service = $this->getDataverseService($this->getRequest());

        $datasetFilesResponse = $service->getDatasetFiles($study);

        $datasetFiles = array();

		foreach ($datasetFilesResponse->data as $data) {
			$datasetFiles[] = ["id" => $data->dataFile->id, "title" => $data->label];
		}

        ksort($datasetFiles);

        return $response->withJson(['items' => $datasetFiles], 200);
    }

    private function getDataverseService($request): DataverseService
    {
        $contextId = $request->getContext()->getId();
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
		$dataverseDispatcher = new DataverseDispatcher($plugin);
		$configuration = $dataverseDispatcher->getDataverseConfiguration($contextId);
		$serviceFactory = new DataverseServiceFactory();

		return $serviceFactory->build($configuration);
    }

}