<?php

class SWORDAPIClient implements IDataAPIClient
{
    private const SAC_PASSWORD = '';

    private const SAC_OBO = '';

    private const SAC_INPROGRESS = false;

    private $swordClient;

    private $installation;

    private $endpoints;

    public function __construct(int $contextId)
    {
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => false));
        $this->installation = new DataverseInstallation($contextId);
        $this->endpoints = new SwordAPIEndpoints($this->installation);
    }

    public function getDataverseData(): array
    {
        $response = $this->swordClient->retrieveDepositReceipt(
            $this->endpoints->getDataverseCollectionUrl(),
            $this->installation->getCredentials->getAPIToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return array(
            'status' => $response->sac_status,
            'content' => $response->sac_xml
        );
    }

    public function getDatasetData(string $persistentId): array
    {
        $response = $this->swordClient->retrieveAtomStatement(
            $this->endpoints->getDatasetStatementUrl($persistentId),
            $this->installation->getCredentials->getAPIToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO
        );

        return array(
            'status' => $response->sac_status,
            'content' => $response->sac_xml
        );
    }
}
