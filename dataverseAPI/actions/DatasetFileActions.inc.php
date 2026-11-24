<?php

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

import('plugins.generic.dataverse.dataverseAPI.actions.interfaces.DatasetFileActionsInterface');
import('plugins.generic.dataverse.dataverseAPI.actions.DataverseActions');
import('plugins.generic.dataverse.classes.exception.DataverseException');

class DatasetFileActions extends DataverseActions implements DatasetFileActionsInterface
{
    private const MAX_DOWNLOAD_REDIRECTS = 5;

    private const IPV4_EMBEDDING_PREFIXES = ['::ffff:0:0/96', '64:ff9b::/96'];

    private const NON_PUBLIC_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/96', '64:ff9b:1::/48', '100::/64', '2001::/23', '2001:db8::/32', '2002::/16',
        'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

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
                $this->sendStorageRequest((string) $currentUri, $options + $this->getStorageConnectionOptions($currentUri));
            } else {
                $this->nativeAPIRequest('GET', (string) $currentUri, $options, false);
            }

            if (!$isRedirect) {
                return;
            }

            if ($redirectUri === '') {
                throw $this->failedDownload('redirect', 'missing_location');
            }

            try {
                $currentUri = UriResolver::resolve($currentUri, new Uri($redirectUri));
            } catch (InvalidArgumentException $e) {
                throw $this->failedDownload('redirect', 'invalid_location');
            }
        }

        throw $this->failedDownload('redirect', 'too_many_redirects');
    }

    private function getStorageConnectionOptions(UriInterface $uri): array
    {
        if ($uri->getScheme() !== 'https') {
            throw $this->failedDownload('redirect_refused', 'https_required');
        }

        $host = trim($uri->getHost(), '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicAddresses([$host]);
            return [];
        }

        $addresses = $this->resolveHostAddresses($host);
        if (empty($addresses) && $this->usesProxy()) {
            return [];
        }
        if (empty($addresses)) {
            throw $this->failedDownload('redirect_refused', 'unresolved_host');
        }
        $this->assertPublicAddresses($addresses);

        $pinnedAddresses = array_map(function (string $address): string {
            return strpos($address, ':') !== false ? '[' . $address . ']' : $address;
        }, $addresses);

        return ['curl' => [CURLOPT_RESOLVE => [$host . ':' . ($uri->getPort() ?? 443) . ':' . implode(',', $pinnedAddresses)]]];
    }

    private function usesProxy(): bool
    {
        if (Config::getVar('proxy', 'https_proxy')) {
            return true;
        }

        foreach (['https_proxy', 'HTTPS_PROXY', 'all_proxy', 'ALL_PROXY'] as $variable) {
            if (getenv($variable)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveHostAddresses(string $host): array
    {
        $addresses = [];
        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $recordType => $addressField) {
            foreach (@dns_get_record($host, $recordType) ?: [] as $record) {
                if (isset($record[$addressField])) {
                    $addresses[] = $record[$addressField];
                }
            }
        }

        if (empty($addresses)) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($addresses));
    }

    private function assertPublicAddresses(array $addresses): void
    {
        foreach ($addresses as $address) {
            if (!$this->isPublicAddress($address)) {
                throw $this->failedDownload('redirect_refused', 'non_public_address');
            }
        }
    }

    private function isPublicAddress(string $address): bool
    {
        $packedAddress = @inet_pton($address);
        if ($packedAddress === false) {
            return false;
        }

        foreach (self::IPV4_EMBEDDING_PREFIXES as $prefix) {
            if ($this->isInRange($packedAddress, $prefix)) {
                return $this->isPublicAddress(inet_ntop(substr($packedAddress, 12)));
            }
        }

        foreach (self::NON_PUBLIC_RANGES as $range) {
            if ($this->isInRange($packedAddress, $range)) {
                return false;
            }
        }

        $flags = defined('FILTER_FLAG_GLOBAL_RANGE')
            ? FILTER_FLAG_GLOBAL_RANGE
            : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($address, FILTER_VALIDATE_IP, $flags) !== false;
    }

    private function isInRange(string $packedAddress, string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range);
        $packedNetwork = inet_pton($network);
        if (strlen($packedNetwork) !== strlen($packedAddress)) {
            return false;
        }

        $fullBytes = intdiv((int) $prefixLength, 8);
        if (substr($packedAddress, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = (int) $prefixLength % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($packedAddress[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
    }

    private function failedDownload(string $category, string $reason): DataverseException
    {
        error_log(json_encode([
            'component' => 'dataverse',
            'operation' => 'download',
            'category' => $category,
            'reason' => $reason,
        ]));

        return new DataverseException(__(self::UNAVAILABLE_MESSAGE), self::UNAVAILABLE_STATUS_CODE);
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
