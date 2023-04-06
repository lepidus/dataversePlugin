<?php

import('plugins.generic.dataverse.dataverseAPI.actions.interfaces.DataverseCollectionActionsInterface');
import('plugins.generic.dataverse.classes.entities.DataverseCollection');
import('plugins.generic.dataverse.dataverseAPI.actions.DataverseActions');

class DataverseCollectionActions extends DataverseActions implements DataverseCollectionActionsInterface
{
    public function get(): DataverseCollection
    {
        $uri = $this->getCurrentDataverseURI();
        $response = $this->nativeAPIRequest('GET', $uri);

        return $this->getDataverseCollection($response);
    }

    public function getRoot(): DataverseCollection
    {
        $uri = $this->getRootDataverseURI();
        $response = $this->nativeAPIRequest('GET', $uri);

        return $this->getDataverseCollection($response);
    }

    public function publish(): void
    {
        $uri = $this->getCurrentDataverseURI() . '/actions/:publish';
        $this->nativeAPIRequest('POST', $uri);
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
