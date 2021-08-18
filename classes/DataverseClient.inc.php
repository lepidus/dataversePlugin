<?php 

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);
define('DATAVERSE_API_VERSION', "v1.1");

class DataverseClient {
    private $apiToken;
    private $dataverseServer;
    private $dataverse;
    private $swordClient;

    public function __construct($apiToken, $dataverseServer, $dataverse) {
        $this->apiToken = $apiToken;
        $this->dataverseServer = $dataverseServer;
        $this->dataverse = $this->formatDvnUri($dataverse);
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => FALSE));
    }

    public function formatDvnUri($dataverseUrl) {
        $dataverseCollection = explode($this->dataverseServer, $dataverseUrl)[1];
        $dvnUri = $this->dataverseServer;
        $dvnUri .= preg_match('/\/dvn$/', $this->dataverseServer) ? '' : '/dvn';
        $dvnUri .= '/api/data-deposit/'. DATAVERSE_API_VERSION . '/swordv2/collection' . $dataverseCollection;

        return $dvnUri;
    }
    
    private function validateCredentials($serviceDocumentRequest) {
		$serviceDocumentClient = $this->swordClient->servicedocument(
			$this->dataverseServer . $serviceDocumentRequest,
			$this->apiToken,
			'********',
			'');

        $dataverseConnectionStatus = isset($serviceDocumentClient) && $serviceDocumentClient->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK;
        return $dataverseConnectionStatus;
    }

    public function checkConnectionWithDataverse() {
		$serviceDocumentRequest = preg_match('/\/dvn$/', $this->dataverseServer) ? '' : '/dvn';
		$serviceDocumentRequest .= '/api/data-deposit/'. DATAVERSE_API_VERSION . '/swordv2/service-document';

		$dataverseConnectionStatus = $this->validateCredentials($serviceDocumentRequest);
		return ($dataverseConnectionStatus);
	}

    public function depositAtomEntry($atomEntryPath) {
        return $this->swordClient->depositAtomEntry($this->dataverse, $this->apiToken, "", '', $atomEntryPath);
    }
}
?>