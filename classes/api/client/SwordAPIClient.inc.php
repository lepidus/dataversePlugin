<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');
import('plugins.generic.dataverse.classes.api.interface.DataverseAPIClient');

class SwordAPIClient implements DataverseAPIClient
{
    private const SAC_PASSWORD = '';
    private const SAC_OBO = '';
    private const SAC_INPROGRESS = false;

    private $swordClient;
    private $configuration;

    public function __construct(int $contextId)
    {
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => false));
        $this->configuration = new DataverseConfiguration($contextId);
    }

    public function getDataDepositBaseUrl(): string
    {
        return $this->configuration->getDataverseServer(). '/dvn/api/data-deposit/'. DATAVERSE_API_VERSION. '/swordv2';
    }

    public function getDataverseServiceDocumentUrl(): string
    {
        return $this->getDataDepositBaseUrl() . '/service-document';
    }

    public function getDataverseDepositUrl(): string
    {
        return $this->getDataDepositBaseUrl() . '/collection' . $this->configuration->getDataverseCollection();
    }

    public function getDataverseReleaseUrl(): string
    {
        return $this->getDataDepositBaseUrl() . '/edit' . $this->configuration->getDataverseCollection();
    }

    private function getEditUri(DataverseStudy $study): string
    {
        return $this->getDataDepositBaseUrl() . '/edit/study/' . $study->getPersistentId();
    }

    private function getEditMediaUri(DataverseStudy $study): string
    {
        return $this->getDataDepositBaseUrl() . '/edit-media/study/' . $study->getPersistentId();
    }

    private function getStatementUri(DataverseStudy $study): string
    {
        return $this->getDataDepositBaseUrl() . '/statement/study/' . $study->getPersistentId();
    }

    private function getDatasetFileUri(int $fileId): string
    {
        return $this->getDataDepositBaseUrl() . '/edit-media/file/' . $fileId;
    }

    public function getDataverseData(): array
    {
        $response = $this->swordClient->retrieveDepositReceipt(
            $this->getDataverseDepositUrl(),
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
            $this->getDataverseServiceDocumentUrl(),
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
            $this->getDataverseDepositUrl(),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $datasetProvider->getDatasetFilePath()
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

    public function addFilesToDataset(DataverseStudy $study, DatasetProvider $datasetProvider)
    {
        $response = $this->swordClient->deposit(
            $this->getEditMediaUri($study),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $datasetProvider->getPackageFilePath(),
            $datasetProvider->getPackaging(),
            $datasetProvider->getContentType(),
            self::SAC_INPROGRESS
        );

        return [
            'status' => $response->sac_status
        ];
    }

    public function getDatasetData(DataverseStudy $study): array
    {
        $response = $this->swordClient->retrieveAtomStatement(
            $this->getStatementUri($study),
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
            $this->getDataverseReleaseUrl(),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $response->sac_status
        ];
    }

    public function publishDataset(DataverseStudy $study): array
    {
        $response = $this->swordClient->completeIncompleteDeposit(
            $this->getEditUri($study),
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
            $this->getDatasetFileUri($fileId),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return [
            'status' => $response->sac_status
        ];
    }
}
