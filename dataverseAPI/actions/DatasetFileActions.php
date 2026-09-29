<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use GuzzleHttp\Psr7\Utils;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use PKP\config\Config;
use PKP\file\FileManager;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces\DatasetFileActionsInterface;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseActions;

class DatasetFileActions extends DataverseActions implements DatasetFileActionsInterface
{
    public function getByDatasetId(string $persistentId): array
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'versions', ':latest', 'files'],
            ['persistentId' => $persistentId]
        );
        $response = $this->nativeAPIRequest('GET', $uri);

        $jsonContent = json_decode($response->getBody(), true);

        return array_map(function (array $file) {
            $datasetFile = new DatasetFile();
            $datasetFile->setId($file['dataFile']['id']);
            $datasetFile->setFileName($file['label']);
            $datasetFile->setOriginalFileName($file['dataFile']['filename']);

            if (!mb_check_encoding($file['label'], 'UTF-8')) {
                $datasetFile->setFileName(mb_convert_encoding($file['label'], 'UTF-8'));
            }
            if (!mb_check_encoding($file['dataFile']['filename'], 'UTF-8')) {
                $datasetFile->setOriginalFileName(mb_convert_encoding($file['dataFile']['filename'], 'UTF-8'));
            }

            return $datasetFile;
        }, $jsonContent['data']);
    }

    public function add(string $persistentId, string $filename, string $filePath): array
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'add'],
            ['persistentId' => $persistentId]
        );
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

        $response = $this->nativeAPIRequest('POST', $uri, $options);
        $content = json_decode((string) $response->getBody(), true);
        $files = $content['data']['files'] ?? [];
        if (!is_array($files) || !$files) {
            throw new DataverseException(__('plugins.generic.dataverse.error.depositPending'));
        }
        return array_map(function ($file): array {
            if (!is_array($file)) {
                throw new DataverseException(__('plugins.generic.dataverse.error.depositPending'));
            }
            $dataFile = $file['dataFile'] ?? [];
            if (!is_array($dataFile) || !isset($dataFile['id']) || !is_int($dataFile['id']) || $dataFile['id'] < 1) {
                throw new DataverseException(__('plugins.generic.dataverse.error.depositPending'));
            }
            return [
                'id' => $dataFile['id'],
                'checksum' => $dataFile['checksum'] ?? null,
                'size' => $dataFile['filesize'] ?? null,
            ];
        }, $files);
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
        $uri = $this->createNativeAPIURI(['access', 'datafile', $datasetFileId]);

        $options = ['sink' => Utils::tryFopen($filePath, 'w')];

        $this->nativeAPIRequest('GET', $uri, $options, false);

        $fileManager = new FileManager();
        $fileManager->downloadByPath($filePath);

        $fileManager->rmtree($datasetFileDir);
    }
}
