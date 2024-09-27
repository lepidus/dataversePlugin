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
        $args = '?persistentId=' . $persistentId;
        $uri = $this->createNativeAPIURI('datasets', ':persistentId' . $args);
        $response = $this->nativeAPIRequest('GET', $uri);

        $datasetFactory = new JsonDatasetFactory($response->getBody());
        return $datasetFactory->getDataset();
    }

    public function getCitation(string $persistentId, ?bool $datasetIsPublished): string
    {
        if (is_null($datasetIsPublished)) {
            $dataset = $this->get($persistentId);
            $datasetIsPublished = $dataset->isPublished();
        }

        if ($datasetIsPublished) {
            $args = '?exporter=dataverse_json&persistentId=' . $persistentId;
            $uri = $this->createNativeAPIURI('datasets', 'export' . $args);
            $response = $this->nativeAPIRequest('GET', $uri);

            $jsonContent = json_decode($response->getBody(), true);
            $citation = $jsonContent['datasetVersion']['citation'];
            $persistentUrl = $jsonContent['persistentUrl'];
            $citation = str_replace(
                $persistentUrl,
                '<a href="' . $persistentUrl . '">' . $persistentUrl . '</a>',
                $citation
            );
            return preg_replace('/,+.UNF[^]]+]/', '', $citation);
        } else {
            return $this->getSWORDCitation($persistentId);
        }
    }

    public function getNativeCitation(string $persistentId): string
    {
        $args = '?persistentId=' . $persistentId;
        $uri = $this->createNativeAPIURI('datasets', ':persistentId', 'versions', ':latest', 'citation' . $args);
        $response = $this->nativeAPIRequest('GET', $uri);

        $jsonContent = json_decode($response->getBody(), true);
        $citation = $jsonContent['data']['message'];
        return preg_replace('/,+.UNF[^]]+]/', '', $citation);
    }

    private function getSWORDCitation(string $persistentId): string
    {
        $uri = $this->createSWORDAPIURI('edit', 'study', $persistentId);
        $response = $this->swordAPIRequest('GET', $uri);

        $doc = new DOMDocument();
        $doc->loadXML($response->getBody());

        $bibliographicCitation = $doc->getElementsByTagName('bibliographicCitation')->item(0)->nodeValue;
        $persistentUrl = $doc->getElementsByTagName('link')->item(4)->getAttribute('href');

        $citation = str_replace(
            $persistentUrl,
            '<a href="' . $persistentUrl . '">' . $persistentUrl . '</a>',
            $bibliographicCitation
        );

        return preg_replace('/,+.UNF[^]]+]/', '', $citation);
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

        $args = '?persistentId=' . $dataset->getPersistentId();
        $uri = $this->createNativeAPIURI('datasets', ':persistentId', 'versions', ':draft', $args);
        $options = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => Utils::tryFopen($packager->getPackagePath(), 'rb')
        ];
        $this->nativeAPIRequest('PUT', $uri, $options);
        $packager->clear();
    }

    public function delete(string $persistendId): void
    {
        $args = '?persistentId=' . $persistendId;
        $uri = $this->createNativeAPIURI('datasets', ':persistentId', 'versions', ':draft' . $args);
        $this->nativeAPIRequest('DELETE', $uri);
    }

    public function publish(string $persistendId): void
    {
        $args = '?persistentId=' . $persistendId . '&type=major';
        $uri = $this->createNativeAPIURI('datasets', ':persistentId', 'actions', ':publish' . $args);

        $this->nativeAPIRequest('POST', $uri);
    }
}
