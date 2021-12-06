<?php 

import('plugins.generic.dataverse.classes.DataverseStudy');
require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);
define('DATAVERSE_PLUGIN_HTTP_STATUS_CREATED', 201);

class DataverseClient {
    private DataverseConfiguration $configuration;
    private SWORDAPPClient $swordClient;

    public function __construct(DataverseConfiguration $configuration)
    {
        $this->configuration = $configuration;
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => FALSE));
    }

    public function getConfiguration(): DataverseConfiguration
    {
        return $this->configuration;
    }
    
    private function validateCredentials(string $serviceDocumentRequest): bool
    {
		$serviceDocumentClient = $this->swordClient->servicedocument($serviceDocumentRequest, $this->configuration->getApiToken(), '', '');
        return isset($serviceDocumentClient) && $serviceDocumentClient->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK;    
    }

    public function checkConnectionWithDataverse(): bool
    {
		return $this->validateCredentials($this->configuration->getDataverseServiceDocumentUrl());
	}

    public function depositAtomEntry(string $atomEntryPath, int $submissionId): DataverseStudy
    {
        $depositReceipt = $this->swordClient->depositAtomEntry($this->configuration->getDataverseDepositUrl(), $this->configuration->getApiToken(), '', '', $atomEntryPath);

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

    public function depositFiles(string $editMediaUri, string $packageFilePath, string $packaging, string $contentType): bool
    {
        $depositReceipt = $this->swordClient->deposit($editMediaUri, $this->configuration->getApiToken(), '', '', $packageFilePath, $packaging, $contentType, false);
        return isset($depositReceipt) && $depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_CREATED;
    }

    public function retrieveDepositReceipt(string $url): SWORDAPPEntry
    {
        $depositReceipt = $this->swordClient->retrieveDepositReceipt($url, $this->configuration->getApiToken(), '', '');
        return ($depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK) ? $depositReceipt : null;
    }

    public function completeIncompleteDeposit(string $url): bool
    {		
        $response = $this->swordClient->completeIncompleteDeposit($url, $this->configuration->getApiToken(), '', '');
        return ($response->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK);
    }

}
