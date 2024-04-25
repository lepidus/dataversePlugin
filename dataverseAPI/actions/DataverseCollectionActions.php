<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces\DataverseCollectionActionsInterface;
use APP\plugins\generic\dataverse\classes\entities\DataverseCollection;
use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseActions;

class DataverseCollectionActions extends DataverseActions implements DataverseCollectionActionsInterface
{
    public function get(): DataverseCollection
    {
        $uri = $this->getCurrentDataverseURI();
        $response = $this->nativeAPIRequest('GET', $uri);

        return $this->createDataverseCollection($response);
    }

    public function getRoot(): DataverseCollection
    {
        $uri = $this->getRootDataverseURI();
        $response = $this->nativeAPIRequest('GET', $uri);

        return $this->createDataverseCollection($response);
    }

    public function publish(): void
    {
        $uri = $this->getCurrentDataverseURI() . '/actions/:publish';
        $this->nativeAPIRequest('POST', $uri);
    }

    private function createDataverseCollection(DataverseResponse $response): DataverseCollection
    {
        $jsonContent = json_decode($response->getBody(), true);
        $dataverseCollectionData = $jsonContent['data'];
        $dataverseCollection = new DataverseCollection();
        $dataverseCollection->setAllData($dataverseCollectionData);

        return $dataverseCollection;
    }
}
