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
                ),
            )
        );
        parent::__construct();
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

        $request = Application::get()->getRequest();
        $contextId = $request->getContext()->getId();
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
		$dispatcher = new DataverseDispatcher($plugin);
		$configuration = $dispatcher->getDataverseConfiguration($contextId);
		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($configuration);

        try {
            $datasetResponse = $service->getDatasetResponse($study);

            if (!empty($datasetResponse)) {
                $metadataBlocks = $datasetResponse->data->latestVersion->metadataBlocks;

                import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');
                $datasetDataCreator = new DataverseDatasetDataCreator();
                $datasetMetadata = $datasetDataCreator->updataMetadataBlocks($metadataBlocks);

                $jsonMetadata = json_encode($datasetMetadata);

                $dataverseResponse = $service->updateDatasetData($jsonMetadata, $study);

                return $response->withJson($dataverseResponse, 200);
            }
            else {
                return $response->withStatus(500)->withJsonError('plugins.generic.dataverse.notification.statusInternalServerError');
            }

        } catch (RuntimeException $e) {
			error_log($e->getMessage());
            $dataverseNotificationMgr = new DataverseNotificationManager();
			$dataverseNotificationMgr->createNotification($e->getCode());
            return $response->withStatus($e->getCode())->withJsonError($e->getMessage());
        }

    }

}