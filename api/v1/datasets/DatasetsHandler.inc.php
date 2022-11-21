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

        $request = Application::get()->getRequest();
        $contextId = $request->getContext()->getId();
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
		$dispatcher = new DataverseDispatcher($plugin);
		$configuration = $dispatcher->getDataverseConfiguration($contextId);
		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($configuration);

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

}