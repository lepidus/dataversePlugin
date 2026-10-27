<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use GuzzleHttp\Psr7\Utils;
use PKP\config\Config;
use PKP\core\PKPString;
use PKP\file\FileManager;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces\DatasetFileActionsInterface;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseActions;

class DatasetFileActions extends DataverseActions implements DatasetFileActionsInterface
{
    protected const INGEST_WAIT_TIMEOUT_SECONDS = 30;
    private const INGEST_POLL_INTERVAL_MICROSECONDS = 250_000;

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
        $this->waitForIngest($persistentId);

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
        $uploadedFiles = json_decode((string) $response->getBody(), true)['data']['files'] ?? null;

        if (!is_array($uploadedFiles) || empty($uploadedFiles)) {
            throw new DataverseException(__('plugins.generic.dataverse.error.depositPending'));
        }

        return array_map(fn ($uploadedFile) => $this->getUploadReceipt($uploadedFile), $uploadedFiles);
    }

    private function getUploadReceipt($uploadedFile): array
    {
        $dataFile = is_array($uploadedFile) ? ($uploadedFile['dataFile'] ?? null) : null;
        $dataFileId = is_array($dataFile) ? ($dataFile['id'] ?? null) : null;

        if (!is_int($dataFileId) || $dataFileId < 1) {
            throw new DataverseException(__('plugins.generic.dataverse.error.depositPending'));
        }

        return [
            'id' => $dataFileId,
            'checksum' => $dataFile['checksum'] ?? null,
            'size' => $dataFile['filesize'] ?? null,
        ];
    }

    private function waitForIngest(string $persistentId): void
    {
        $deadline = microtime(true) + static::INGEST_WAIT_TIMEOUT_SECONDS;
        $locksUri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'locks'],
            ['persistentId' => $persistentId, 'type' => 'Ingest']
        );

        while (($remainingSeconds = $deadline - microtime(true)) > 0) {
            $response = $this->nativeAPIRequest('GET', $locksUri, [
                'connect_timeout' => min(5, $remainingSeconds),
                'timeout' => $remainingSeconds,
            ]);
            $locks = json_decode($response->getBody(), true)['data'] ?? null;

            if (!is_array($locks) || !array_is_list($locks)) {
                throw new DataverseException(__('plugins.generic.dataverse.error.exception.unavailable'), 503);
            }

            if (empty($locks)) {
                return;
            }

            usleep((int) min(self::INGEST_POLL_INTERVAL_MICROSECONDS, max(0, $deadline - microtime(true)) * 1_000_000));
        }

        throw new DataverseException(__('plugins.generic.dataverse.error.ingestTimeout'));
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

        $filePath = $datasetFileDir . DIRECTORY_SEPARATOR . 'datasetFile';
        $uri = $this->createNativeAPIURI(['access', 'datafile', $datasetFileId]);

        $options = ['sink' => Utils::tryFopen($filePath, 'w')];
        $fileManager = new FileManager();

        try {
            $this->nativeAPIRequest('GET', $uri, $options, false);

            $mediaType = PKPString::mime_content_type($filePath, pathinfo($filename, PATHINFO_EXTENSION));
            $downloadName = preg_replace('/["\\\\[:cntrl:]]/', '', $filename);
            $fileManager->downloadByPath($filePath, $mediaType, false, $downloadName);
        } finally {
            $fileManager->rmtree($datasetFileDir);
        }
    }
}
