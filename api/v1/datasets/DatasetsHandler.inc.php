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

        $datasetResponse = $service->getDatasetResponse($study);

        $metadataBlocks = $datasetResponse->data->latestVersion->metadataBlocks;

        foreach ($requestParams as $key => $value) {
            foreach ($metadataBlocks->citation->fields as $metadata) {
                if ($metadata->typeName == $key) {
                    if (gettype($metadata->value) == 'array') {
                        foreach ($metadata->value as $class) {
                            $attr = $metadata->typeName . 'Value';
                            $class->$attr->value = $value;
                        }
                    }
                    else {
                        $metadata->value = $value;
                    }
                }
                elseif ($metadata->typeName == 'subject' && in_array('N/A', $metadata->value)) {
                    $metadata->value = ['Other'];
                }
            }
        }

        $datasetMetadata = new stdClass();
        $datasetMetadata->metadataBlocks = $metadataBlocks;

        $jsonMetadata = json_encode($datasetMetadata);

        $dataverseResponse = $service->updateDatasetData($jsonMetadata, $study);

        return $response->withJson($dataverseResponse, 200);
    }

}