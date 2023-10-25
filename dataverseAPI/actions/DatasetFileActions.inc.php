<?php

use GuzzleHttp\Psr7\Utils;

import('plugins.generic.dataverse.dataverseAPI.actions.interfaces.DatasetFileActionsInterface');
import('plugins.generic.dataverse.dataverseAPI.actions.DataverseActions');

class DatasetFileActions extends DataverseActions implements DatasetFileActionsInterface
{
    public function getByDatasetId(string $persistentId): array
    {
        $args = '?persistentId=' . $persistentId;
        $uri = $this->createNativeAPIURI('datasets', ':persistentId', 'versions', ':latest', 'files' . $args);
        $response = $this->nativeAPIRequest('GET', $uri);

        $jsonContent = json_decode($response->getBody(), true);

        return array_map(function (array $file) {
            import('plugins.generic.dataverse.classes.entities.DatasetFile');
            $datasetFile = new DatasetFile();
            $datasetFile->setId($file['dataFile']['id']);
            $datasetFile->setFileName($file['label']);
            $datasetFile->setOriginalFileName($file['dataFile']['filename']);
            return $datasetFile;
        }, $jsonContent['data']);
    }

    public function add(string $persistentId, string $filename, string $filePath): void
    {
        $args = '?persistentId=' . $persistentId;
        $uri = $this->createNativeAPIURI('datasets', ':persistentId', 'add' . $args);
        $options = [
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => Utils::tryFopen($filePath, 'rb'),
                    'filename' => $filename
                ],
                [
                    'name' => 'jsonData',
                    'contents' => json_encode(['label' => $filename])
                ]
            ],
        ];

        $this->nativeAPIRequest('POST', $uri, $options);
    }

    public function delete(int $datasetFileId): void
    {
        $uri = $this->createSWORDAPIURI('edit-media', 'file', $datasetFileId);
        $this->swordAPIRequest('DELETE', $uri);
    }

    public function download(int $datasetFileId, string $filename): void
    {
        $filesDir = Config::getVar('files', 'files_dir');
        $datasetFileDir = tempnam($filesDir, 'datasetFile');
        unlink($datasetFileDir);
        mkdir($datasetFileDir);

        $filePath = $datasetFileDir . DIRECTORY_SEPARATOR . $filename;
        $uri = $this->createNativeAPIURI('access', 'datafile', $datasetFileId);

        $options = ['sink' => Utils::tryFopen($filePath, 'w')];

        $this->nativeAPIRequest('GET', $uri, $options);

        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();
        $fileManager->downloadByPath($filePath);

        $fileManager->rmtree($datasetFileDir);
    }
}
