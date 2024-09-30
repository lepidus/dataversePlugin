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
        $cache = $this->cacheManager->getFileCache(
            $this->contextId,
            'dataverse_collection',
            [$this, 'cacheDismiss']
        );

        $dataverseCollection = $cache->getContents();
        $currentCacheTime = time() - $cache->getCacheTime();

        if (is_null($dataverseCollection) || $currentCacheTime > self::ONE_DAY_SECONDS) {
            $cache->flush();

            $uri = $this->getCurrentDataverseURI();
            $response = $this->nativeAPIRequest('GET', $uri);
            $dataverseCollection = $this->createDataverseCollection($response);

            $cache->setEntireCache($dataverseCollection);
        }

        return $dataverseCollection;
    }

    public function getRoot(): DataverseCollection
    {
        $cache = $this->cacheManager->getFileCache(
            $this->contextId,
            'root_dataverse_collection',
            [$this, 'cacheDismiss']
        );

        $rootDataverseCollection = $cache->getContents();
        $currentCacheTime = time() - $cache->getCacheTime();

        if (is_null($rootDataverseCollection) || $currentCacheTime > self::ONE_DAY_SECONDS) {
            $cache->flush();

            $uri = $this->getRootDataverseURI();
            $response = $this->nativeAPIRequest('GET', $uri);
            $rootDataverseCollection = $this->createDataverseCollection($response);

            $cache->setEntireCache($rootDataverseCollection);
        }

        return $rootDataverseCollection;
    }

    public function getLicenses(): array
    {
        $cache = $this->cacheManager->getFileCache(
            $this->contextId,
            'dataverse_licenses',
            [$this, 'cacheDismiss']
        );

        $dataverseLicenses = $cache->getContents();
        $currentCacheTime = time() - $cache->getCacheTime();

        if (is_null($dataverseLicenses) || $currentCacheTime > self::ONE_DAY_SECONDS) {
            $cache->flush();

            $uri = $this->createNativeAPIURI('licenses');
            $response = $this->nativeAPIRequest('GET', $uri);
            $dataverseLicenses = json_decode($response->getBody(), true);

            $cache->setEntireCache($dataverseLicenses);
        }

        return $dataverseLicenses['data'] ?? [];
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
