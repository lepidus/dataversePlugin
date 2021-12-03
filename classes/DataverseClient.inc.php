<?php 

import('plugins.generic.dataverse.classes.DataverseStudy');
require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);
define('DATAVERSE_PLUGIN_HTTP_STATUS_CREATED', 201);
define('DATAVERSE_API_VERSION', "v1.1");

class DataverseClient {
    private $configuration;
    private $dataverseUri;
    private $swordClient;
    private $dataverseAlias;

    public function __construct(DataverseConfiguration $configuration) {
        $this->configuration = $configuration;
        $this->dataverseUri = $this->formatDataverseUri($configuration->getDataverseUrl());
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => FALSE));
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function getDataverseUri()
    {
        return $this->dataverseUri;
    }

    public function getDataverseAlias() {
        return $this->dataverseAlias;
    }

    public function formatDataverseUri($dataverseUrl) {
        $this->dataverseAlias = explode($this->configuration->getDataverseServer(), $dataverseUrl)[1];
        $dataverseUri = $this->configuration->getDataverseServer();
        $dataverseUri .= preg_match('/\/dvn$/', $this->configuration->getDataverseServer()) ? '' : '/dvn';
        $dataverseUri .= '/api/data-deposit/'. DATAVERSE_API_VERSION . '/swordv2/collection' . $this->dataverseAlias;

        return $dataverseUri;
    }
    
    private function validateCredentials($serviceDocumentRequest) {
		$serviceDocumentClient = $this->swordClient->servicedocument(
			$this->configuration->getDataverseServer() . $serviceDocumentRequest,
			$this->configuration->getApiToken(),
			'',
			'');

        $dataverseConnectionStatus = isset($serviceDocumentClient) && $serviceDocumentClient->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK;
        return $dataverseConnectionStatus;
    }

    public function checkConnectionWithDataverse() {
		$serviceDocumentRequest = preg_match('/\/dvn$/', $this->configuration->getDataverseServer()) ? '' : '/dvn';
		$serviceDocumentRequest .= '/api/data-deposit/'. DATAVERSE_API_VERSION . '/swordv2/service-document';

		$dataverseConnectionStatus = $this->validateCredentials($serviceDocumentRequest);
		return ($dataverseConnectionStatus);
	}

    public function depositAtomEntry($atomEntryPath, $submissionId) {
        $depositReceipt = $this->swordClient->depositAtomEntry($this->dataverseUri, $this->configuration->getApiToken(), '', '', $atomEntryPath);

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
        $depositReceipt = $this->swordClient->deposit($editMediaUri, $this->configuration->getApiToken(), '', '', $packageFilePath, $packaging, $contentType, false);

        $depositStatus = isset($depositReceipt) && $depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_CREATED;
        return $depositStatus;
    }

    public function retrieveDepositReceipt($request) {
        $depositReceipt = $this->swordClient->retrieveDepositReceipt($request, $this->configuration->getApiToken(), '', '');
        
        return ($depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK) ? $depositReceipt : null;
    }

    public function completeIncompleteDeposit($request) {		
        $response = $this->swordClient->completeIncompleteDeposit($request, $this->configuration->getApiToken(), '', '');
                        
        return ($response->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK);
    }

}
