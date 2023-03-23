<?php

import('plugins.generic.dataverse.classes.dataverseAPI.operations.NativeAPIDataverseOperations');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.interfaces.DatasetOperationsInterface');

class NativeAPIDatasetOperations extends NativeAPIDataverseOperations implements DatasetOperationsInterface
{
    public function addFile(string $persistentId, DatasetFile $file): DatasetFile
    {
        $apiURL = $this->createAPIURL(['datasets', ':persistentId', 'add?persistentId=', $persistentId]);
        $requestType = 'POST';
        $headers = array_merge(
            $this->getDataverseHeaders(),
            ['Content-Type' => 'application/octet-stream']
        );
        $options = [
            'headers' => $headers,
            'body' => GuzzleHttp\Psr7\Utils::tryFopen($file->getPath(), 'rb')
        ];

        $response = $this->executeRequest($requestType, $apiURL, $options);

        if ($response->getStatusCode() !== HTTP_STATUS_CREATED) {
            throw new Exception('Error adding file: ' . $response->getMessage());
        }

        return $this->retrieveDatasetFile($response->getData());
    }

    private function retrieveDatasetFile(string $responseData): DatasetFile
    {
        $fileData = json_decode($responseData, true)['data'];
        $file = new DatasetFile();
        $file->setId($fileData['dataFile']['id']);
        $file->setFilename($fileData['label']);
        $file->originalFileName($fileData['dataFile']['filename']);

        return $file;
    }
}
