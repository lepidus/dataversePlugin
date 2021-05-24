<?php 

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);

class DataverseRepository {
    private $apiToken;
    private $dvnURI;

    public function __construct($apiToken, $dvnURI) {
        $this->apiToken = $apiToken;
        $this->dvnURI = $dvnURI;
    }
    
    private function validateCredentials($serviceDocumentRequest) {
        $client = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => FALSE));
		$serviceDocumentClient = $client->servicedocument(
			$this->dvnURI . $serviceDocumentRequest,
			$this->apiToken,
			'********',
			'');

        $dataverseConnectionStatus = isset($serviceDocumentClient) && $serviceDocumentClient->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK;
        return $dataverseConnectionStatus;
    }

    public function checkConnectionWithDataverseInstance($apiVersion) {
		$serviceDocumentRequest = preg_match('/\/dvn$/', $this->dvnUri) ? '' : '/dvn';
		$serviceDocumentRequest .= '/api/data-deposit/v'. $apiVersion . '/swordv2/service-document';

		$dataverseConnectionStatus = $this->validateCredentials($serviceDocumentRequest);
		return ($dataverseConnectionStatus);
	}

}
?>