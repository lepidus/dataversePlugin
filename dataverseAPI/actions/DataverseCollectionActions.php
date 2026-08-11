<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use Illuminate\Support\Facades\Cache;
use APP\plugins\generic\dataverse\classes\entities\DataverseCollection;
use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces\DataverseCollectionActionsInterface;

class DataverseCollectionActions extends DataverseActions implements DataverseCollectionActionsInterface
{
    public function get(): DataverseCollection
    {
        $cacheKey = self::getCacheKey('dataverse_collection', $this->contextId);
        $dataverseCollection = Cache::get($cacheKey);

        if (is_null($dataverseCollection)) {
            $uri = $this->getCurrentDataverseURI();
            $response = $this->nativeAPIRequest('GET', $uri);
            $dataverseCollection = $this->createDataverseCollection($response);

            if (!empty($dataverseCollection->getName())) {
                Cache::put($cacheKey, $dataverseCollection, self::ONE_DAY_SECONDS);
            }
        }

        return $dataverseCollection;
    }

    public function getRoot(): DataverseCollection
    {
        $cacheKey = self::getCacheKey('root_dataverse_collection', $this->contextId);
        $rootDataverseCollection = Cache::get($cacheKey);

        if (is_null($rootDataverseCollection)) {
            $uri = $this->getRootDataverseURI();
            $response = $this->nativeAPIRequest('GET', $uri);
            $rootDataverseCollection = $this->createDataverseCollection($response);

            if (!empty($rootDataverseCollection->getName())) {
                Cache::put($cacheKey, $rootDataverseCollection, self::ONE_DAY_SECONDS);
            }
        }

        return $rootDataverseCollection;
    }

    public function getLicenses(): array
    {
        $cacheKey = self::getCacheKey('dataverse_licenses', $this->contextId);
        $dataverseLicenses = Cache::get($cacheKey);

        if (is_null($dataverseLicenses)) {
            $uri = $this->createNativeAPIURI(['licenses']);
            $response = $this->nativeAPIRequest('GET', $uri);
            $dataverseLicenses = json_decode($response->getBody(), true);

            Cache::put($cacheKey, $dataverseLicenses, self::ONE_DAY_SECONDS);
        }

        return $dataverseLicenses['data'] ?? [];
    }

    public function getApiTokenExpirationDate(): string
    {
        $uri = $this->createNativeAPIURI(['users', 'token']);
        $response = $this->nativeAPIRequest('GET', $uri);
        $decodedResponse = json_decode($response->getBody(), true);

        $message = $decodedResponse['data']['message'];
        preg_match('/expires on (\d{4}-\d{2}-\d{2})/', $message, $matches);

        return $matches[1] ?? '';
    }

    public function getRequiredMetadata(): array
    {
        $cacheKey = self::getCacheKey('dataverse_required_metadata', $this->contextId);
        $dataverseRequiredMetadata = Cache::get($cacheKey);

        if (is_null($dataverseRequiredMetadata)) {
            $args = 'returnDatasetFieldTypes=true';
            $uri = $this->getCurrentDataverseURI() . '/metadatablocks?' . $args;
            $response = $this->nativeAPIRequest('GET', $uri);
            $responseBody = json_decode($response->getBody(), true);
            $metadataBlocks = $responseBody['data'] ?? [];

            $dataverseRequiredMetadata = $this->extractRequiredMetadata($metadataBlocks);

            Cache::put($cacheKey, $dataverseRequiredMetadata, self::ONE_DAY_SECONDS);
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
            'datasetContactName', 'datasetContactEmail', 'depositor', 'publicationCitation',
            'publicationRelationType', 'producerName'
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
                $fields = $field['childFields'] ?? [$field];

                foreach ($fields as $flattenedField) {
                    $fieldName = $flattenedField['name'];
                    if (!isset($flattenedFields[$fieldName])) {
                        $flattenedFields[$fieldName] = $flattenedField;
                    }
                }
            }
        }

        return array_values($flattenedFields);
    }
}
