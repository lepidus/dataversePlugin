<?php 

class DataverseRepository {
    
    public function validateCredentials($DataverseAuthForm, $serviceDocumentRequest){

        $client = $DataverseAuthForm->_plugin->_initSwordClient();
		$serviceDocumentClient = $client->servicedocument(
			$DataverseAuthForm->getData('dvnUri') . $serviceDocumentRequest,
			$DataverseAuthForm->getData('apiToken'),
			'********',
			'');

        $dataverseConnectionStatus = isset($serviceDocumentClient) && $serviceDocumentClient->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK;
        return $dataverseConnectionStatus;
    }

}
?>