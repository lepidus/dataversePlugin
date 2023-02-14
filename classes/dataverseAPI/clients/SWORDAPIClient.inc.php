<?php

import('plugins.generic.dataverse.classes.dataverseAPI.clients.interfaces.IDepositAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.SWORDAPIEndpoints');
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

    public function __construct(DataverseServer $server)
    {
        $this->swordClient = new SWORDAPPClient(array(CURLOPT_SSL_VERIFYPEER => false));
        $this->endpoints = new SWORDAPIEndpoints($server);
        $this->server = $server;
    }

    public function getDatasetPackager(Dataset $datataset): DatasetPackager
    {
        return new SWORDAPIDatasetPackager($datataset);
    }

    public function depositDataset(DatasetPackager $packager): DataverseResponse
    {
        $response = $this->swordClient->depositAtomEntry(
            $this->endpoints->getDataverseCollectionUrl(),
            $this->server->getCredentials()->getAPIToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $packager->getPackagePath()
        );

        $data = [
            'editUri' => $response->sac_edit_iri->__toString(),
            'editMediaUri' => $response->sac_edit_media_iri->__toString(),
            'statementUri' => $response->sac_state_iri_atom->__toString(),
        ];

        foreach ($response->sac_links as $link) {
            if ($link->sac_linkrel == 'alternate') {
                $data['persistentUri'] = $link->sac_linkhref->__toString();
                $data['persistentId'] = $this->retrievePersistentId($link->sac_linkhref->__toString());
            }
        }

        return new DataverseResponse(
            $response->sac_status,
            $response->sac_statusmessage,
            json_encode($data)
        );
    }

    public function depositDatasetFiles(string $persistentId, DatasetPackager $packager): DataverseResponse
    {
        $atomPackager = $packager->getAtomPackager();
        $response = $this->swordClient->deposit(
            $this->endpoints->getDatasetEditMediaUrl($persistentId),
            $this->server->getCredentials()->getAPIToken(),
            self::SAC_PASSWORD,
            self::SAC_OBO,
            $atomPackager->getPackageFilePath(),
            $atomPackager->getPackaging(),
            $atomPackager->getContentType(),
            self::SAC_INPROGRESS
        );

        return new DataverseResponse(
            $response->sac_status,
            $response->sac_statusmessage,
            $response->toString()
        );
    }

    private function retrievePersistentId(string $persistentUri)
    {
        preg_match('/(?<=https:\/\/doi.org\/)(.)*/', $persistentUri, $matches);
        return 'doi:' . $matches[0];
    }
}
