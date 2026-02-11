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

            if (!empty($dataverseCollection->getName())) {
                $cache->setEntireCache($dataverseCollection);
            }
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

            if (!empty($rootDataverseCollection->getName())) {
                $cache->setEntireCache($rootDataverseCollection);
            }
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

    public function getApiTokenExpirationDate(): string
    {
        $uri = $this->createNativeAPIURI('users', 'token');
        $response = $this->nativeAPIRequest('GET', $uri);
        $decodedResponse = json_decode($response->getBody(), true);

        $message = $decodedResponse['data']['message'];
        preg_match('/expires on (\d{4}-\d{2}-\d{2})/', $message, $matches);

        return $matches[1] ?? '';
    }

    public function getRequiredMetadata(): array
    {
        $cache = $this->cacheManager->getFileCache(
            $this->contextId,
            'dataverse_required_metadata',
            [$this, 'cacheDismiss']
        );

        $dataverseRequiredMetadata = $cache->getContents();
        $currentCacheTime = time() - $cache->getCacheTime();

        if (is_null($dataverseRequiredMetadata) || $currentCacheTime > self::ONE_DAY_SECONDS) {
            $cache->flush();

            $args = 'returnDatasetFieldTypes=true&onlyDisplayedOnCreate=true';
            $uri = $this->getCurrentDataverseURI() . '/metadatablocks?' . $args;
            $response = $this->nativeAPIRequest('GET', $uri);
            $responseBody = json_decode($response->getBody(), true);
            $metadataBlocks = $responseBody['data'] ?? [];

            $dataverseRequiredMetadata = $this->extractRequiredMetadata($metadataBlocks);

            $cache->setEntireCache($dataverseRequiredMetadata);
        }

        return $dataverseRequiredMetadata;
    }

    public function publish(): void
    {
        $uri = $this->getCurrentDataverseURI() . '/actions/:publish';
        $this->nativeAPIRequest('POST', $uri);
    }

    private function createDataverseCollection(DataverseResponse $response): DataverseCollection
    {
        $jsonContent = json_decode($response->getBody(), true);
        if (
            $jsonContent['status'] != 'OK'
            || empty($jsonContent['data'])
            || !isset($jsonContent['data']['name'])
        ) {
            $dummyDataverseCollection = new DataverseCollection();
            $dummyDataverseCollection->setName('');
            return $dummyDataverseCollection;
        }

        $dataverseCollectionData = $jsonContent['data'];
        $dataverseCollection = new DataverseCollection();
        $dataverseCollection->setAllData($dataverseCollectionData);

        return $dataverseCollection;
    }

    private function extractRequiredMetadata(array $metadataBlocks): array
    {
        $requiredMetadata = [];

        foreach ($metadataBlocks as $block) {
            if (!isset($block['fields']) || !is_array($block['fields'])) {
                continue;
            }

            $filteredFields = $this->filterRequiredFields($block['fields']);

            if (!empty($filteredFields)) {
                $requiredMetadata[$block['name']] = [
                    'name' => $block['name'],
                    'displayName' => $block['displayName'],
                    'fields' => $filteredFields
                ];
            }
        }

        return $requiredMetadata;
    }

    private function filterRequiredFields(array $fields): array
    {
        $metadataToFilter = [
            'title', 'dsDescriptionValue', 'subject', 'authorName', 'authorIdentifierScheme', 'subject',
            'datasetContactName', 'datasetContactEmail', 'depositor', 'publicationCitation'
        ];
        $filteredFields = [];

        foreach ($fields as $key => $field) {
            if (in_array($field['name'], $metadataToFilter)) {
                continue;
            }

            if ($this->isRequiredField($field, $metadataToFilter)) {
                $filteredFields[$key] = $field;
            }
        }

        return $filteredFields;
    }

    private function isRequiredField(array &$field, $metadataToFilter): bool
    {
        $hasRequiredChildren = false;

        if (isset($field['childFields']) && is_array($field['childFields'])) {
            $field['childFields'] = array_filter(
                $field['childFields'],
                fn ($child) => $child['isRequired'] && !in_array($child['name'], $metadataToFilter)
            );

            $hasRequiredChildren = !empty($field['childFields']);
        }

        return ($field['isRequired'] ?? false) || $hasRequiredChildren;
    }

    public function getFlattenedFields(array $metadataBlocks): array
    {
        $flattenedFields = [];

        foreach ($metadataBlocks as $block) {
            foreach ($block['fields'] as $field) {
                if (isset($field['childFields'])) {
                    foreach ($field['childFields'] as $childField) {
                        $flattenedFields[] = $childField;
                    }
                    continue;
                }
                $flattenedFields[] = $field;
            }
        }

        return $flattenedFields;
    }
}
