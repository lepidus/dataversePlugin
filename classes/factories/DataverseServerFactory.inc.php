<?php

import('plugins.generic.dataverse.classes.entities.DataverseServer');

class DataverseServerFactory
{
    public function createDataverseServer(int $contextId): DataverseServer
    {
        $credentials = DAORegistry::getDAO('DataverseCredentialsDAO')->get($contextId);
        $dataverseServerUrl = $this->retrieveDataverseServerUrl($credentials->getDataverseUrl());
        $dataverseCollection = $this->retrieveDataverseCollection($credentials->getDataverseUrl());

        return new DataverseServer($credentials, $dataverseServerUrl, $dataverseCollection);
    }

    private function retrieveDataverseServerUrl(string $dataverseUrl): string
    {
        preg_match('/https:\/\/(.)*?(?=\/)/', $dataverseUrl, $matches);
        return $matches[0];
    }

    private function retrieveDataverseCollection(string $dataverseUrl): string
    {
        $explodedUrl = explode('/', $dataverseUrl);
        return end($explodedUrl);
    }
}
