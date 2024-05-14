<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use GuzzleHttp\Psr7\Utils;
use PKP\config\Config;
use PKP\file\FileManager;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces\DatasetFileActionsInterface;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseActions;

class DatasetFileActions extends DataverseActions implements DatasetFileActionsInterface
{
    public function getByDatasetId(string $persistentId): array
    {
        $args = '?persistentId=' . $persistentId;
        $uri = $this->createNativeAPIURI('datasets', ':persistentId', 'versions', ':latest', 'files' . $args);
        $response = $this->nativeAPIRequest('GET', $uri);

        $jsonContent = json_decode($response->getBody(), true);

        return array_map(function (array $file) {
            $datasetFile = new DatasetFile();
            $datasetFile->setId($file['dataFile']['id']);
            $datasetFile->setFileName($file['label']);
            $datasetFile->setOriginalFileName($file['dataFile']['filename']);

            $encodedChar = 'Ã';
            if (str_contains($file['label'], $encodedChar)) {
                $datasetFile->setFileName(utf8_decode($file['label']));
            }
            if (str_contains($file['dataFile']['filename'], $encodedChar)) {
                $datasetFile->setOriginalFileName(utf8_decode($file['dataFile']['filename']));
            }

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

        $fileManager = new FileManager();
        $fileManager->downloadByPath($filePath);

        $fileManager->rmtree($datasetFileDir);
    }
}
