<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');
import('plugins.generic.dataverse.classes.api.interfaces.DataverseAPIClient');
import('plugins.generic.dataverse.classes.NewDataverseConfiguration');
import('plugins.generic.dataverse.classes.api.endpoints.SwordAPIEndpoints');
import('plugins.generic.dataverse.classes.api.providers.SwordAPIDatasetProvider');

class SwordAPIClient implements DataverseAPIClient
{
    private const SAC_PASSWORD = '';
    private const SAC_OBO = '';
    private const SAC_INPROGRESS = false;

    private $swordClient;
    private $configuration;
    private $endpoints;

    public function __construct(int $contextId)
    {
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => false));
        $this->configuration = new NewDataverseConfiguration($contextId);
        $this->endpoints = new SwordAPIEndpoints(
            $this->configuration->getDataverseServer(),
            $this->configuration->getDataverseCollection()
        );
    }

    public function getDatasetProvider(Submission $submission): DatasetProvider
    {
        return new SwordAPIDatasetProvider($submission);
    }

    public function getDataverseData(): array
    {
        $response = $this->swordClient->retrieveDepositReceipt(
            $this->endpoints->getDataverseDepositUrl(),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $response->sac_status
        ];
    }

    public function getDataverseServerColletions(): array
    {
        $response = $this->swordClient->servicedocument(
            $this->endpoints->getDataverseServiceDocumentUrl(),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $response->sac_status
        ];
    }

    public function depositDataset(DatasetProvider $datasetProvider): array
    {
        $response = $this->swordClient->depositAtomEntry(
            $this->endpoints->getDataverseDepositUrl(),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $datasetProvider->getDatasetPath()
        );

        $persistentId = null;
        foreach ($response->sac_links as $link) {
            if ($link->sac_linkrel == 'alternate') {
                $persistentId = $this->retrievePersistentId($link->sac_linkhref);
            }
        }

        return [
            'status' => $response->sac_status,
            'persistentId' => $persistentId
        ];
    }

    public function depositDatasetFiles(string $persistentId, DatasetProvider $datasetProvider): array
    {
        $package = $datasetProvider->getPackage();
        $response = $this->swordClient->deposit(
            $this->endpoints->getEditMediaUri($persistentId),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $package->getPackageFilePath(),
            $package->getPackaging(),
            $package->getContentType(),
            self::SAC_INPROGRESS
        );

        return [
            'status' => $response->sac_status
        ];
    }

    public function getDatasetData(string $persistentId): array
    {
        $response = $this->swordClient->retrieveAtomStatement(
            $this->endpoints->getStatementUri($study),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $statement->sac_status
        ];
    }

    public function publishDataverse(): array
    {
        $response = $this->swordClient->completeIncompleteDeposit(
            $this->endpoints->getDataverseReleaseUrl(),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $response->sac_status
        ];
    }

    public function publishDataset(string $persistentId): array
    {
        $response = $this->swordClient->completeIncompleteDeposit(
            $this->endpoints->getEditUri($study),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $response->sac_status
        ];
    }

    public function deleteDatasetFile(int $fileId): array
    {
        $response = $this->swordClient->deleteResourceContent(
            $this->endpoints->getDatasetFileUri($fileId),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $response->sac_status
        ];
    }

    private function retrievePersistentId(string $persistentUri)
    {
        preg_match('/(?<=https:\/\/doi.org\/)(.)*/', $persistentUri, $matches);
        $persistentId =  "doi:" . $matches[0];

        return $persistentId;
    }
}
