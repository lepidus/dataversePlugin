<?php

import('plugins.generic.dataverse.classes.dataverseAPI.operations.nativeAPI.NativeAPIOperations');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.nativeAPI.interfaces.DatasetOperationsInterface');

class DatasetOperations extends NativeAPIOperations implements DatasetOperationsInterface
{
    public function addFile(string $persistentId, DatasetFile $file): DatasetFile
    {
        $apiURL = $this->createAPIURL(['datasets', ':persistentId', 'add?persistentId=' . $persistentId]);
        $requestType = 'POST';
        $headers = $this->getDataverseHeaders();
        $options = [
            'headers' => $headers,
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => GuzzleHttp\Psr7\Utils::tryFopen($file->getPath(), 'r'),
                    'filename' => $file->getOriginalFileName()
                ]
            ],
        ];

        $response = $this->executeRequest($requestType, $apiURL, $options);

        if ($response->getStatusCode() !== HTTP_STATUS_OK) {
            throw new Exception('Error adding file: ' . $response->getMessage());
        }

        return $this->retrieveDatasetFile($response);
    }

    private function retrieveDatasetFile(DataverseAPIResponse $response): DatasetFile
    {
        $fileResponse = $response->getBodyAsArray();
        $fileData = $fileResponse['data'];
        $file = new DatasetFile();
        $file->setId($fileData['files'][0]['dataFile']['id']);
        $file->setFilename($fileData['files'][0]['label']);
        $file->setOriginalFileName($fileData['files'][0]['dataFile']['filename']);

        return $file;
    }
}
