<?php

import('plugins.generic.dataverse.dataverseAPI.actions.interfaces.DataverseCollectionActionsInterface');
import('plugins.generic.dataverse.dataverseAPI.native.NativeAPI');
import('plugins.generic.dataverse.classes.entities.DataverseCollection');

class DataverseCollectionActions implements DataverseCollectionActionsInterface
{
    public function get(): DataverseCollection
    {
        $nativeAPI = new NativeAPI();
        $uri = $nativeAPI->getCurrentDataverseURI();
        $response = $nativeAPI->makeRequest('GET', $uri);

        return $this->getDataverseCollection($response);
    }

    public function getRoot(): DataverseCollection
    {
        $nativeAPI = new NativeAPI();
        $uri = $nativeAPI->getRootDataverseURI();
        $response = $nativeAPI->makeRequest('GET', $uri);

        return $this->getDataverseCollection($response);
    }

    public function publish(): void
    {
        $nativeAPI = new NativeAPI();
        $uri = $nativeAPI->getCurrentDataverseURI() . '/actions/:publish';
        $nativeAPI->makeRequest('POST', $uri);
    }

    private function createDataverseCollection(DataverseReponse $response): DataverseCollection
    {
        $jsonContent = json_decode($response->getBody(), true);
        $dataverseCollectionData = $jsonContent['data'];
        $dataverseCollection = new DataverseCollection();
        $dataverseCollection->setAllData($dataverseCollectionData);

        return $dataverseCollection;
    }
}
