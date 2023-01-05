<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');
import('plugins.generic.dataverse.classes.api.providers.SwordAPIDatasetProvider');
import('plugins.generic.dataverse.classes.api.clients.interfaces.DepositAPIClient');
import('plugins.generic.dataverse.classes.api.endpoints.SwordAPIEndpoints');
import('plugins.generic.dataverse.classes.api.response.DataverseResponse');
import('plugins.generic.dataverse.classes.NewDataverseConfiguration');

class SwordAPIClient implements DepositAPIClient
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

    public function newDatasetProvider(Submission $submission): DatasetProvider
    {
        return new SwordAPIDatasetProvider($submission);
    }

    public function depositDataset(DatasetProvider $datasetProvider): DataverseResponse
    {
        $response = $this->swordClient->depositAtomEntry(
            $this->endpoints->getDataverseDepositUrl(),
            $this->configuration->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $datasetProvider->getDatasetPath()
        );

        $persistentUri = null;
        $persistentId = null;
        foreach ($response->sac_links as $link) {
            if ($link->sac_linkrel == 'alternate') {
                $persistentUri = $link->sac_linkhref;
                $persistentId = $this->retrievePersistentId($persistentUri);
            }
        }

        return new DataverseResponse(
            $response->sac_status,
            [
                'editUri' => $this->endpoints->getEditUri($persistentId),
                'editMediaUri' => $this->endpoints->getEditMediaUri($persistentId),
                'statementUri' => $this->endpoints->getStatementUri($persistentId),
                'persistentUri' => $persistentUri,
                'persistentId' => $persistentId,
            ]
        );
    }

    public function depositDatasetFiles(string $persistentId, DatasetProvider $datasetProvider): DataverseResponse
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

        return new DataverseResponse($response->sac_status);
    }

    private function retrievePersistentId(string $persistentUri): string
    {
        preg_match('/(?<=https:\/\/doi.org\/)(.)*/', $persistentUri, $matches);
        $persistentId =  "doi:" . $matches[0];

        return $persistentId;
    }
}
