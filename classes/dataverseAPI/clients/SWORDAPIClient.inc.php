<?php

import('plugins.generic.dataverse.classes.dataverseAPI.clients.interfaces.IDepositAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.packagers.SWORDAPIDatasetPackager');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class SWORDAPIClient implements IDepositAPIClient
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

    public function getDatasetPackager(Dataset $datataset): DatasetPackager
    {
        return new SWORDAPIDatasetPackager($datataset);
    }

    public function depositDataset(DatasetPackager $packager): DataverseResponse
    {
        $response = $this->swordClient->depositAtomEntry(
            $this->endpoints->getDataverseCollectionUrl(),
            $this->server->getApiToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $packager->getPackagePath()
        );

        return new DataverseResponse(
            $response->sac_status,
            $response->sac_statusmessage,
            $response->toString()
        );
    }
}
