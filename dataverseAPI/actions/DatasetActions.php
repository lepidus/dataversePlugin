<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use DOMDocument;
use GuzzleHttp\Psr7\Utils;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetIdentifier;
use APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces\DatasetActionsInterface;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseActions;
use APP\plugins\generic\dataverse\dataverseAPI\packagers\NativeAPIDatasetPackager;
use APP\plugins\generic\dataverse\classes\factories\JsonDatasetFactory;

class DatasetActions extends DataverseActions implements DatasetActionsInterface
{
    public function get(string $persistentId): Dataset
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'versions'],
            ['persistentId' => $persistentId]
        );
        $response = $this->nativeAPIRequest('GET', $uri);

        $datasetFactory = new JsonDatasetFactory($response->getBody());
        return $datasetFactory->getDataset();
    }

    public function isInCurrentCollection(string $persistentId): bool
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId'],
            ['persistentId' => $persistentId, 'returnOwners' => 'true']
        );
        $dataset = json_decode($this->nativeAPIRequest('GET', $uri)->getBody(), true)['data'] ?? [];

        if (!array_key_exists('isPartOf', $dataset)) {
            return $this->collectionTreeContainsDataset($this->dataverseAlias, (int) ($dataset['id'] ?? 0));
        }

        $owner = $dataset['isPartOf'];
        while (is_array($owner)) {
            if (($owner['type'] ?? null) === 'DATAVERSE' && strcasecmp($owner['identifier'] ?? '', $this->dataverseAlias) === 0) {
                return true;
            }
            $owner = $owner['isPartOf'] ?? null;
        }

        return false;
    }

    private function collectionTreeContainsDataset(string $collection, int $datasetId): bool
    {
        $pendingCollections = [$collection];
        $visitedCollections = [];

        while ($pendingCollections) {
            $currentCollection = array_shift($pendingCollections);
            $visitedCollections[] = $currentCollection;

            $uri = $this->createNativeAPIURI(['dataverses', $currentCollection, 'contents']);
            $contents = json_decode($this->nativeAPIRequest('GET', $uri)->getBody(), true)['data'] ?? [];

            foreach ($contents as $item) {
                if (($item['type'] ?? null) === 'dataset' && (int) ($item['id'] ?? 0) === $datasetId) {
                    return true;
                }
                $childCollection = (string) ($item['id'] ?? '');
                if (($item['type'] ?? null) === 'dataverse' && !in_array($childCollection, $visitedCollections, true)) {
                    $pendingCollections[] = $childCollection;
                }
            }
        }

        return false;
    }

    public function getCitation(string $persistentId, ?bool $datasetIsPublished): array
    {
        if (is_null($datasetIsPublished)) {
            $dataset = $this->get($persistentId);
            $datasetIsPublished = $dataset->isPublished();
        }

        if ($datasetIsPublished) {
            $uri = $this->createNativeAPIURI(
                ['datasets', 'export'],
                ['exporter' => 'dataverse_json', 'persistentId' => $persistentId]
            );
            $response = $this->nativeAPIRequest('GET', $uri);

            $jsonContent = json_decode($response->getBody(), true);
            $citation = $this->formatCitation(
                $jsonContent['datasetVersion']['citation'],
                $jsonContent['persistentUrl']
            );
        } else {
            $citation = $this->getSWORDCitation($persistentId);
        }

        return ['datasetIsPublished' => $datasetIsPublished, 'citation' => $citation];
    }

    private function getSWORDCitation(string $persistentId): string
    {
        $uri = $this->createSWORDAPIURI('edit', 'study', $persistentId);
        $response = $this->swordAPIRequest('GET', $uri);

        $doc = new DOMDocument();
        $doc->loadXML($response->getBody());

        $bibliographicCitation = $doc->getElementsByTagName('bibliographicCitation')->item(0)->nodeValue;
        $persistentUrl = $doc->getElementsByTagName('link')->item(4)->getAttribute('href');

        return $this->formatCitation($bibliographicCitation, $persistentUrl);
    }

    private function formatCitation(string $citation, string $persistentUrl): string
    {
        $citation = preg_replace('/,+.UNF[^]]+]/', '', $citation);
        $escapedCitation = htmlspecialchars($citation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedPersistentUrl = htmlspecialchars($persistentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return str_replace(
            $escapedPersistentUrl,
            '<a href="' . $escapedPersistentUrl . '">' . $escapedPersistentUrl . '</a>',
            $escapedCitation
        );
    }

    public function getDatasetLocks(string $persistentId): array
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'locks'],
            ['persistentId' => $persistentId]
        );
        $response = $this->nativeAPIRequest('GET', $uri);
        $jsonContent = json_decode($response->getBody(), true);

        return $jsonContent['data'];
    }

    public function create(Dataset $dataset): DatasetIdentifier
    {
        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->createDatasetPackage();

        $uri = $this->getCurrentDataverseURI() . '/datasets';
        $options = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => Utils::tryFopen($packager->getPackagePath(), 'rb')
        ];
        $response = $this->nativeAPIRequest('POST', $uri, $options);

        $jsonContent = json_decode($response->getBody(), true);
        $datasetIdentifier = new DatasetIdentifier();
        $datasetIdentifier->setAllData($jsonContent['data']);
        $packager->clear();

        return $datasetIdentifier;
    }

    public function update(Dataset $dataset): void
    {
        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->createDatasetPackage();

        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'versions', ':draft'],
            ['persistentId' => $dataset->getPersistentId()]
        );
        $options = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => Utils::tryFopen($packager->getPackagePath(), 'rb')
        ];
        $this->nativeAPIRequest('PUT', $uri, $options);
        $packager->clear();
    }

    public function delete(string $persistendId): void
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'versions', ':draft'],
            ['persistentId' => $persistendId]
        );
        $this->nativeAPIRequest('DELETE', $uri);
    }

    public function publish(string $persistendId): void
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'actions', ':publish'],
            ['persistentId' => $persistendId, 'type' => 'major']
        );

        $this->nativeAPIRequest('POST', $uri);
    }
}
