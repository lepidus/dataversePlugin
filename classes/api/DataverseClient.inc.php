<?php 

import('plugins.generic.dataverse.classes.DataverseNotificationManager');
require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);
define('DATAVERSE_PLUGIN_HTTP_STATUS_CREATED', 201);
define('DATAVERSE_PLUGIN_HTTP_STATUS_FILE_DELETED', 204);
define('DATAVERSE_PLUGIN_HTTP_STATUS_BAD_REQUEST', 400);
define('DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED', 401);
define('DATAVERSE_PLUGIN_HTTP_STATUS_FORBIDDEN', 403);
define('DATAVERSE_PLUGIN_HTTP_STATUS_NOT_FOUND', 404);
define('DATAVERSE_PLUGIN_HTTP_STATUS_PRECONDITION_FAILED', 412);
define('DATAVERSE_PLUGIN_HTTP_STATUS_PAYLOAD_TOO_LARGE', 413);
define('DATAVERSE_PLUGIN_HTTP_STATUS_UNSUPPORTED_MEDIA_TYPE', 415);
define('DATAVERSE_PLUGIN_HTTP_STATUS_INTERNAL_SERVER_ERROR', 500);
define('DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE', 503);
define('DATAVERSE_PLUGIN_HTTP_UNKNOWN_ERROR', 0);

class DataverseClient {
    private $configuration;
    private $swordClient;

    public function __construct(DataverseConfiguration $configuration)
    {
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

    public function checkConnectionWithDataverse(): bool
    {
		$serviceDocument = $this->getServiceDocument();
        return isset($serviceDocument) && $serviceDocument->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK;
	}

    public function depositAtomEntry(string $atomEntryPath): ?SWORDAPPEntry
    {
        $depositReceipt = $this->swordClient->depositAtomEntry($this->configuration->getDataverseDepositUrl(), $this->configuration->getApiToken(), '', '', $atomEntryPath);
        return $depositReceipt;
    }

    public function depositFiles(string $editMediaUri, string $packageFilePath, string $packaging, string $contentType): bool
    {
        $depositReceipt = $this->swordClient->deposit($editMediaUri, $this->configuration->getApiToken(), '', '', $packageFilePath, $packaging, $contentType, false);
        return isset($depositReceipt) && $depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_CREATED;
    }

    public function retrieveAtomStatement(string $url): ?SWORDAPPStatement
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();
        $dataverseUrl = $this->configuration->getDataverseUrl();
        $params = ['dataverseUrl' => $dataverseUrl];

        $statement = $this->swordClient->retrieveAtomStatement($url, $this->configuration->getApiToken(), '', '');

        if (!empty($statement) && !empty($statement->sac_xml)) {
			return $statement;
		} else {
            throw new RuntimeException($dataverseNotificationMgr->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_UNKNOWN_ERROR));
            return null;
        }
    }

    public function retrieveDepositReceipt(string $url): ?SWORDAPPEntry
    {
        $depositReceipt = $this->swordClient->retrieveDepositReceipt($url, $this->configuration->getApiToken(), '', '');

        $dataverseNotificationMgr = new DataverseNotificationManager();
        $dataverseUrl = $this->configuration->getDataverseUrl();
        $params = ['dataverseUrl' => $dataverseUrl];

        if($depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK)
            return $depositReceipt;
        else
            throw new RuntimeException(
                $dataverseNotificationMgr->getNotificationMessage($depositReceipt->sac_status, $params),
                $depositReceipt->sac_status
            );
        return null;
    }

    public function completeIncompleteDeposit(string $url): bool
    {
        $response = $this->swordClient->completeIncompleteDeposit($url, $this->configuration->getApiToken(), '', '');
        if ($response->sac_status != DATAVERSE_PLUGIN_HTTP_STATUS_OK) {
            $dataverseNotificationMgr = new DataverseNotificationManager();
            $dataverseUrl = $this->configuration->getDataverseUrl();
            $params = ['dataverseUrl' => $dataverseUrl];
            throw new RuntimeException(
                $dataverseNotificationMgr->getNotificationMessage($response->sac_status, $params),
                $response->sac_status
            );
        }
        return ($response->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK);
    }

    public function deleteFile(string $url): bool
    {
        $response = $this->swordClient->deleteResourceContent($url, $this->configuration->getApiToken(), '', '');

        if ($response->sac_status != DATAVERSE_PLUGIN_HTTP_STATUS_FILE_DELETED) {
            throw new RuntimeException(
                $response->sac_statusmessage,
                $response->sac_status
            );
            return false;
        }

        return true;
    }

    public function deleteDataset(string $url): ?string
    {
        $dataverseRequest = $this->curlInit($url);
        curl_setopt($dataverseRequest, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $this->execRequest($dataverseRequest);
    }

    public function retrieveJsonRepresentation(string $apiUrl): ?string
    {
        $dataverseRequest = $this->curlInit($apiUrl);
        return $this->execRequest($dataverseRequest);
    }

    public function updateMetadata($apiUrl, $jsonFile): ?string
    {
        $headers = ['Content-Type: application/json'];

        $dataverseRequest = $this->curlInit($apiUrl, $headers);

        curl_setopt($dataverseRequest, CURLOPT_PUT, true);
        curl_setopt($dataverseRequest, CURLOPT_INFILE, fopen($jsonFile, 'rb'));
        curl_setopt($dataverseRequest, CURLOPT_INFILESIZE, filesize($jsonFile));

        return $this->execRequest($dataverseRequest);
    }

    public function getDatasetFiles(string $apiUrl): ?string
    {
        $dataverseRequest = $this->curlInit($apiUrl);
        return $this->execRequest($dataverseRequest);
    }

    public function depositFileToDataset(string $apiUrl, CURLFile $file): ?string
    {
        $dataverseRequest = $this->curlInit($apiUrl);

        curl_setopt($dataverseRequest, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($dataverseRequest, CURLOPT_POST, true);
        curl_setopt($dataverseRequest, CURLOPT_POSTFIELDS, ['file' => $file]);

        return $this->execRequest($dataverseRequest);
    }

    private function curlInit(string $url, array $headers = [])
    {
        $dataverseRequest = curl_init();

        array_push($headers, 'X-Dataverse-key:' . $this->configuration->getApiToken());
        
		curl_setopt($dataverseRequest, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($dataverseRequest, CURLOPT_URL, $url);
        curl_setopt($dataverseRequest, CURLOPT_HTTPHEADER, $headers);

        return $dataverseRequest;
    }

    private function execRequest($request): ?string
    {
        $resp = curl_exec($request);
        if (empty($resp)) $cError = curl_error($request);
        $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        if ($status == DATAVERSE_PLUGIN_HTTP_STATUS_OK) {
            return $resp;
        }
        else {
            if (!empty($curlError)) {
                throw new RuntimeException($curlError, $status);
            }
            else {
                $error = json_decode($resp);
                $errorMessage = empty($error->message) ? $resp : $error->message;
                throw new RuntimeException($errorMessage, $status);
            }
        }
    }
    
}
