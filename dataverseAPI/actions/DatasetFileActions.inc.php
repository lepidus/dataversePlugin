<?php

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

import('plugins.generic.dataverse.dataverseAPI.actions.interfaces.DatasetFileActionsInterface');
import('plugins.generic.dataverse.dataverseAPI.actions.DataverseActions');
import('plugins.generic.dataverse.classes.exception.DataverseException');

class DatasetFileActions extends DataverseActions implements DatasetFileActionsInterface
{
    private const MAX_DOWNLOAD_REDIRECTS = 5;

    public function getByDatasetId(string $persistentId): array
    {
        $uri = $this->createNativeAPIURI(
            ['datasets', ':persistentId', 'versions', ':latest', 'files'],
            ['persistentId' => $persistentId]
        );
        $response = $this->nativeAPIRequest('GET', $uri);

        $jsonContent = json_decode($response->getBody(), true);

        return array_map(function (array $file) {
            import('plugins.generic.dataverse.classes.entities.DatasetFile');
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

        $filePath = $datasetFileDir . DIRECTORY_SEPARATOR . 'datasetFile';
        $uri = $this->createNativeAPIURI(['access', 'datafile', $datasetFileId]);

        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();

        try {
            touch($filePath);
            $this->transferFile($uri, $filePath);
            $mediaType = PKPString::mime_content_type($filePath, pathinfo($filename, PATHINFO_EXTENSION));
            $downloadName = preg_replace('/["\\\\[:cntrl:]]/', '', $filename);
            $fileManager->downloadByPath($filePath, $mediaType, false, $downloadName);
        } finally {
            $fileManager->rmtree($datasetFileDir);
        }
    }

    private function transferFile(string $uri, string $filePath): void
    {
        $isRedirect = false;
        $redirectUri = '';
        $options = [
            'sink' => $filePath,
            'allow_redirects' => false,
            'connect_timeout' => 5,
            'timeout' => $this->getRequestTimeout('download'),
            'on_headers' => function (ResponseInterface $response) use (&$isRedirect, &$redirectUri): void {
                $isRedirect = $response->getStatusCode() >= 300 && $response->getStatusCode() < 400;
                $redirectUri = $response->getHeaderLine('Location');
            },
        ];

        $currentUri = new Uri($uri);
        $leftServer = false;

        for ($transfer = 0; $transfer <= self::MAX_DOWNLOAD_REDIRECTS; $transfer++) {
            $leftServer = $leftServer || !$this->isServerOrigin($currentUri);

            if ($leftServer) {
                $this->sendStorageRequest((string) $currentUri, $options);
            } else {
                $this->nativeAPIRequest('GET', (string) $currentUri, $options, false);
            }

            if (!$isRedirect) {
                return;
            }

            if ($redirectUri === '') {
                break;
            }

            try {
                $currentUri = UriResolver::resolve($currentUri, new Uri($redirectUri));
            } catch (InvalidArgumentException $e) {
                break;
            }
        }

        throw new DataverseException(__(self::UNAVAILABLE_MESSAGE), self::UNAVAILABLE_STATUS_CODE);
    }

    private function sendStorageRequest(string $uri, array $options): void
    {
        try {
            $this->sendRequest('GET', $uri, $options, 'download');
        } catch (DataverseException $e) {
            if ($e->getCode() === DataverseException::AUTH_ERROR_STATUS_CODE) {
                throw new DataverseException(__(self::UNAVAILABLE_MESSAGE), self::UNAVAILABLE_STATUS_CODE, $e);
            }

            throw $e;
        }
    }
}
