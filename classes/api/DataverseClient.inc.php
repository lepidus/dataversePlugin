<?php 

import('plugins.generic.dataverse.classes.study.DataverseStudy');
import('plugins.generic.dataverse.classes.dispatchers.DataverseNotificationDispatcher');
require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);
define('DATAVERSE_PLUGIN_HTTP_STATUS_CREATED', 201);
define('DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED', 403);
define('DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE', 503);

class DataverseClient {
    private $configuration;
    private $swordClient;
    private $dataverseNotification;

    public function __construct(DataverseConfiguration $configuration, DataversePlugin $plugin)
    {
        $this->dataverseNotification = new DataverseNotificationDispatcher($plugin);
        $this->configuration = $configuration;
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => FALSE));
    }

    public function getConfiguration(): DataverseConfiguration
    {
        return $this->configuration;
    }

    private function getServiceDocument(): SWORDAPPServiceDocument
    {
        return $this->swordClient->servicedocument($this->configuration->getDataverseServiceDocumentUrl(), $this->configuration->getApiToken(), '', '');
    }

    public function getDataverseTermsOfUse(): string
    {
		$dataverseDepositUrl = $this->configuration->getDataverseDepositUrl();
        $serviceDocument = $this->getServiceDocument();

		foreach ($serviceDocument->sac_workspaces as $workspace) {
			foreach ($workspace->sac_collections as $collection) {
				if ($collection->sac_href[0] == $dataverseDepositUrl) {
					$dataverseTermsOfUse = $collection->sac_collpolicy;
					break;
				}
			}
		}

        return $dataverseTermsOfUse;
    }

    public function checkConnectionWithDataverse(): bool
    {
		$serviceDocument = $this->getServiceDocument();
        return isset($serviceDocument) && $serviceDocument->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK;
	}

    public function depositAtomEntry(string $atomEntryPath, int $submissionId): DataverseStudy
    {
        $depositReceipt = $this->swordClient->depositAtomEntry($this->configuration->getDataverseDepositUrl(), $this->configuration->getApiToken(), '', '', $atomEntryPath);
        
        $study = null;
        switch ($depositReceipt->sac_status) {
            case DATAVERSE_PLUGIN_HTTP_STATUS_CREATED:
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

                $this->dataverseNotification->sendNotification(DATAVERSE_PLUGIN_HTTP_STATUS_CREATED);
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE:
                $this->dataverseNotification->sendNotification(DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE);
                break;
        }
		return $study;
    }

    public function depositFiles(string $editMediaUri, string $packageFilePath, string $packaging, string $contentType): bool
    {
        $depositReceipt = $this->swordClient->deposit($editMediaUri, $this->configuration->getApiToken(), '', '', $packageFilePath, $packaging, $contentType, false);
        return isset($depositReceipt) && $depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_CREATED;
    }

    public function retrieveAtomStatement(string $url): SWORDAPPStatement
    {
        return $this->swordClient->retrieveAtomStatement($url, $this->configuration->getApiToken(), '', '');
    }

    public function retrieveDepositReceipt(string $url): ?SWORDAPPEntry
    {
        $depositReceipt = $this->swordClient->retrieveDepositReceipt($url, $this->configuration->getApiToken(), '', '');
        if ($depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK) {
            return $depositReceipt;
        } else {
            $this->dataverseNotification->sendNotification(DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED);
            return null;
        }
    }

    public function completeIncompleteDeposit(string $url): bool
    {		
        $response = $this->swordClient->completeIncompleteDeposit($url, $this->configuration->getApiToken(), '', '');
        return ($response->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK);
    }

}
