<?php 

import('plugins.generic.dataverse.classes.DataverseStudy');
require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);
define('DATAVERSE_PLUGIN_HTTP_STATUS_CREATED', 201);
define('DATAVERSE_API_VERSION', "v1.1");
define('DATAVERSE_API_PASSWORD', "******");

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
			DATAVERSE_API_PASSWORD,
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

    public function depositAtomEntry($atomEntryPath, $submissionId) {
        $depositReceipt = $this->swordClient->depositAtomEntry($this->dataverse, $this->apiToken, DATAVERSE_API_PASSWORD, '', $atomEntryPath);

        $study = null;
		if ($depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_CREATED) {
			$study = new DataverseStudy();
			$study->setSubmissionId($submissionId);
			$study->setEditUri($depositReceipt->sac_edit_iri);
			$study->setEditMediaUri($depositReceipt->sac_edit_media_iri);
			$study->setStatementUri($depositReceipt->sac_state_iri_atom);
			$study->setDataCitation($depositReceipt->sac_dcterms['bibliographicCitation'][0]);
			
			foreach ($depositReceipt->sac_links as $link) {
				if ($link->sac_linkrel == 'alternate') {
					$study->setPersistentUri($link->sac_linkhref);
					break;
				}
			}
			$dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');			 
			$dataverseStudyDao->insertStudy($study);
		}
		return $study;
    }

    public function depositFiles($editMediaUri, $packageFilePath, $packaging, $contentType) {
        $depositReceipt = $this->swordClient->deposit($editMediaUri, $this->apiToken, DATAVERSE_API_PASSWORD, '', $packageFilePath, $packaging, $contentType, false);

        $depositStatus = isset($depositReceipt) && $depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_CREATED;
        return $depositStatus;
    }

    public function completeIncompleteDeposit($study) {		
        $response = $this->swordClient->completeIncompleteDeposit(
                        $study->getEditUri(),
                        $this->apiToken,
                        DATAVERSE_API_PASSWORD,	
                        '');
        
        $studyReleased = ($response->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK); 
        if ($studyReleased) {
            $depositReceipt = $this->swordClient->retrieveDepositReceipt(
                            $study->getEditUri(), 
                            $this->apiToken,
                            DATAVERSE_API_PASSWORD,
                            '');

            if ($depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK) {
                $study->setDataCitation($depositReceipt->sac_dcterms['bibliographicCitation'][0]);
                $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
                $dataverseStudyDao->updateStudy($study);
            }		
        }
        return $studyReleased;
    }
}
