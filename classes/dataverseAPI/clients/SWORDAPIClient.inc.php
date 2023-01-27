<?php

class SWORDAPIClient implements IDataAPIClient
{
    private const SAC_PASSWORD = '';

    private const SAC_OBO = '';

    private const SAC_INPROGRESS = false;

    private $swordClient;

    private $server;

    private $endpoints;

    public function __construct(int $contextId)
    {
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => false));
        $this->server = new DataverseServer($contextId);
        $this->endpoints = new SwordAPIEndpoints($this->server);
    }

    public function getDataverseData(): array
    {
        $response = $this->swordClient->retrieveDepositReceipt(
            $this->endpoints->getDataverseCollectionUrl(),
            $this->server->getCredentials()->getAPIToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return new DataverseResponse(
            $response->sac_status,
            $response->sac_phase,
            $response->sac_xml
        );
    }

    public function getDatasetData(string $persistentId): array
    {
        $response = $this->swordClient->retrieveAtomStatement(
            $this->endpoints->getDatasetStatementUrl($persistentId),
            $this->server->getCredentials()->getAPIToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return new DataverseResponse(
            $response->sac_status,
            $response->sac_phase,
            $response->sac_xml
        );
    }
}
