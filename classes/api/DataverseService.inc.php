<?php

import('plugins.generic.dataverse.classes.api.DataverseClient');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.study.DataverseStudy');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');
import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');
import('plugins.generic.dataverse.classes.creators.DataversePackageCreator');

class DataverseService
{
    private $dataverseClient;
    private $submission;

    public function __construct(DataverseClient $dataverseClient)
    {
        $this->dataverseClient = $dataverseClient;
    }

    public function getClient(): DataverseClient
    {
        return $this->dataverseClient;
    }

    public function setSubmission(Submission $submission, User $submissionUser): void
    {
        $submissionAdapterCreator = new SubmissionAdapterCreator();
        $submissionAdapter = $submissionAdapterCreator->createSubmissionAdapter($submission, $submissionUser);
        $this->submission = $submissionAdapter;
    }

    public function getDataverseName(): ?string
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();
        try {
            $receipt = $this->dataverseClient->retrieveDepositReceipt($this->dataverseClient->getConfiguration()->getDataverseDepositUrl());
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $dataverseNotificationMgr->createNotification($e->getCode());
        }

        return $receipt->sac_title;
    }

    public function createPackage(): DataversePackageCreator
    {
        $package = new DataversePackageCreator();
        $factory = new SubmissionDatasetFactory($this->submission);
        $dataset = $factory->getDataset();
        $package->loadMetadata($dataset);
        $package->createAtomEntry();

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        foreach ($this->submission->getFiles() as $draftDatasetFile) {
            $file = $temporaryFileManager->getFile($draftDatasetFile->getData('fileId'), $draftDatasetFile->getData('userId'));
            $package->addFileToPackage($file->getFilePath(), $file->getOriginalFileName());
        }
        $package->createPackage();

        return $package;
    }

    public function depositPackage(): void
    {
        $package = $this->createPackage();
        if ($package->hasFiles()) {
            $study = $this->depositStudy($package);
        }
        if (!empty($study)) {
            $this->defineAdditionalMetadata($study);
            $this->deleteDraftDatasetFiles();
        }
    }

    public function depositStudy(DataversePackageCreator $package): ?DataverseStudy
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();
        try {
            $depositReceipt = $this->dataverseClient->depositAtomEntry($package->getAtomEntryPath());
            $study = $this->insertDataverseStudy($depositReceipt);
            if (!is_null($study)) {
                $this->dataverseClient->depositFiles(
                    $study->getEditMediaUri(),
                    $package->getPackageFilePath(),
                    $package->getPackaging(),
                    $package->getContentType()
                );
            }
            $dataverseNotificationMgr->createNotification(DATAVERSE_PLUGIN_HTTP_STATUS_CREATED);
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $dataverseNotificationMgr->createNotification($e->getCode());
        }

        return $study;
    }

    private function retrievePersistentId(string $persistentUri)
    {
        preg_match('/(?<=https:\/\/doi.org\/)(.)*/', $persistentUri, $matches);
        $persistentId =  "doi:" . $matches[0];

        return $persistentId;
    }

    private function insertDataverseStudy(SWORDAPPEntry $depositReceipt): ?DataverseStudy
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();
        $dataverseUrl = $this->dataverseClient->getConfiguration()->getDataverseUrl();

        $params = ['dataverseUrl' => $dataverseUrl];

        $study = null;
        if ($depositReceipt->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_CREATED) {
            $study = new DataverseStudy();
            $study->setSubmissionId($this->submission->getId());
            $study->setEditUri($depositReceipt->sac_edit_iri);
            $study->setEditMediaUri($depositReceipt->sac_edit_media_iri);
            $study->setStatementUri($depositReceipt->sac_state_iri_atom);

            foreach ($depositReceipt->sac_links as $link) {
                if ($link->sac_linkrel == 'alternate') {
                    $study->setPersistentUri($link->sac_linkhref);
                    $persistentId = $this->retrievePersistentId($study->getPersistentUri());
                    $study->setPersistentId($persistentId);
                    break;
                }
            }
            $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
            $dataverseStudyDao->insertStudy($study);
        } else {
            throw new RuntimeException(
                $dataverseNotificationMgr->getNotificationMessage($depositReceipt->sac_status, $params),
                $depositReceipt->sac_status
            );
        }
        return $study;
    }

    public function defineAdditionalMetadata($study): void
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();

        try {
            $dataverseServer = $this->dataverseClient->getConfiguration()->getDataverseServer();
            $url =  $dataverseServer . '/api/datasets/:persistentId/editMetadata?persistentId=' . $study->getPersistentId() . '&replace=true';

            $factory = new SubmissionDatasetFactory($this->submission);
            $dataset = $factory->getDataset();

            $metadata = [
                'datasetSubject' => $dataset->getSubject(),
                'datasetAuthor' => $dataset->getAuthors(),
                'datasetContact' => $dataset->getContact(),
                'datasetDepositor' => $dataset->getDepositor()
            ];

            $datasetDataCreator = new DataverseDatasetDataCreator();
            $updatedFields = $datasetDataCreator->createMetadataFields($metadata);

            $updatedFieldsJson = json_encode($updatedFields);

            $jsonFilePath = $this->createJsonFile($updatedFieldsJson);

            $this->dataverseClient->updateMetadata($url, $jsonFilePath);
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $dataverseNotificationMgr->createNotification($e->getCode());
        }
    }

    private function deleteDraftDatasetFiles()
    {
        try {
            $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
            foreach ($this->submission->getFiles() as $draftDatasetFile) {
                $draftDatasetFileDAO->deleteObject($draftDatasetFile);
            }
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
        }
    }

    public function dataverseIsReleased(): bool
    {
        $depositReceipt = $this->dataverseClient->retrieveDepositReceipt($this->dataverseClient->getConfiguration()->getDataverseDepositUrl());

        $released = false;
        if (!empty($depositReceipt)) {
            $depositReceiptXml = new SimpleXMLElement($depositReceipt->sac_xml);
            $releasedNodes = $depositReceiptXml->children('http://purl.org/net/sword/terms/state')->dataverseHasBeenReleased;
            if (!empty($releasedNodes) && $releasedNodes[0] == 'true') {
                $released = true;
            }
        }
        return $released;
    }

    public function studyIsReleased(DataverseStudy $study): bool
    {
        $statement = $this->getStudyStatement($study->getStatementUri());
        $studyReleased = false;
        if (!empty($statement)) {
            $sac_xml = new SimpleXMLElement($statement->sac_xml);
            foreach ($sac_xml->children()->category as $category) {
                if ($category->attributes()->term == 'latestVersionState') {
                    if ($category == 'RELEASED') {
                        $studyReleased = true;
                    }
                    break;
                }
            }
        }
        return $studyReleased;
    }

    public function getStudyStatement(string $url): ?SWORDAPPStatement
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();
        try {
            $statement = $this->dataverseClient->retrieveAtomStatement($url);

            return $statement;
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $dataverseNotificationMgr->createNotification($e->getCode());
        }
        return null;
    }

    public function getStudyCitation(DataverseStudy $study): ?string
    {
        $statement = $this->getStudyStatement($study->getEditUri());
        if (!empty($statement)) {
            $sac_xml = new SimpleXMLElement($statement->sac_xml);
            $citation = $sac_xml->bibliographicCitation[0];
            $persistentUrl = $sac_xml->link[4]->attributes()->href[0];
            $citation = str_replace(
                $persistentUrl,
                '<a href="' . $persistentUrl . '">' . $persistentUrl . '</a>',
                $citation
            );
            return $citation;
        }
        return null;
    }

    public function releaseDataverse(): bool
    {
        return $this->dataverseClient->completeIncompleteDeposit($this->dataverseClient->getConfiguration()->getDataverseReleaseUrl());
    }

    public function releaseStudy(): bool
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();
        $studyPublished = false;
        try {
            $dvReleased = $this->dataverseIsReleased();
            if (!$dvReleased) {
                $dvReleased = $this->releaseDataverse();
            }

            $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
            $study = $dataverseStudyDao->getStudyBySubmissionId($this->submission->getId());

            if (!empty($study)) {
                $studyPublished = $this->dataverseClient->completeIncompleteDeposit($study->getEditUri());
                if ($studyPublished) {
                    $dataverseNotificationMgr->createNotification(DATAVERSE_PLUGIN_HTTP_STATUS_OK);
                }
            }
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $dataverseNotificationMgr->createNotification($e->getCode());
        }

        return $studyPublished;
    }

    private function createJsonFile(string $jsonMatadata): string
    {
        $fileDir = tempnam('/tmp', 'datasetUpdateMetadata');
        unlink($fileDir);
        mkdir($fileDir);

        $fileJsonPath = $fileDir . DIRECTORY_SEPARATOR . 'metadata.json';
        $jsonFile = fopen($fileJsonPath, 'w');
        fwrite($jsonFile, $jsonMatadata);
        fclose($jsonFile);

        return $fileJsonPath;
    }

    public function getDatasetResponse($study): ?stdClass
    {
        try {
            $dataverseServer = $this->dataverseClient->getConfiguration()->getDataverseServer();
            $apiUrl = $dataverseServer . '/api/datasets/:persistentId/?persistentId=' . $study->getPersistentId();

            $response = $this->dataverseClient->retrieveJsonRepresentation($apiUrl);

            if (!empty($response)) {
                return json_decode($response);
            }
        } catch (RuntimeException $e) {
            $dataverseNotificationMgr = new DataverseNotificationManager();
            $dataverseNotificationMgr->createNotification($e->getCode());
            error_log($e->getMessage());
        }
        return null;
    }

    public function updateDatasetData(string $jsonMatadata, DataverseStudy $study): ?stdClass
    {
        $dataverseNotificationMgr = new DataverseNotificationManager();
        try {
            $fileJsonPath = $this->createJsonFile($jsonMatadata);

            $dataverseServer = $this->dataverseClient->getConfiguration()->getDataverseServer();
            $apiUrl =
                $dataverseServer .
                '/api/datasets/:persistentId/editMetadata?persistentId=' .
                $study->getPersistentId() .
                '&replace=true';

            $response = $this->dataverseClient->updateMetadata($apiUrl, $fileJsonPath);

            if (!empty($response)) {
                $dataverseNotificationMgr->createCustomNotification(
                    NOTIFICATION_TYPE_SUCCESS,
                    __('plugins.generic.dataverse.notification.metadataUpdated')
                );
                return json_decode($response);
            }
        } catch (RuntimeException $e) {
            $dataverseNotificationMgr->createNotification($e->getCode());
            error_log($e->getMessage());
        }
        return null;
    }

    public function getDatasetFiles(DataverseStudy $study): ?stdClass
    {
        try {
            $dataverseServer = $this->dataverseClient->getConfiguration()->getDataverseServer();
            $apiUrl = $dataverseServer . '/api/datasets/:persistentId/versions/:latest/files?persistentId=' . $study->getPersistentId();

            $files = $this->dataverseClient->getDatasetFiles($apiUrl);

            if (!empty($files)) {
                return json_decode($files);
            }
        } catch (RuntimeException $e) {
            $dataverseNotificationMgr = new DataverseNotificationManager();
            $dataverseNotificationMgr->createNotification($e->getCode());
            error_log($e->getMessage());
        }
        return null;
    }

    public function addDatasetFile(DataverseStudy $study, TemporaryFile $file): ?string
    {
        try {
            $dataverseServer = $this->dataverseClient->getConfiguration()->getDataverseServer();
            $apiUrl = $dataverseServer . '/api/datasets/:persistentId/add?persistentId=' . $study->getPersistentId();

            $datasetFile = new CURLFile($file->getFilePath(), null, $file->getOriginalFileName());

            return $this->dataverseClient->depositFileToDataset($apiUrl, $datasetFile);
        } catch (RuntimeException $e) {
            $dataverseNotificationMgr = new DataverseNotificationManager();
            $dataverseNotificationMgr->createNotification($e->getCode());
            error_log($e->getMessage());
        }
        return null;
    }

    public function deleteDatasetFile(DataverseStudy $study, string $fileId): ?bool
    {
        try {
            $url = preg_replace('/study\/.*/', 'file/' . $fileId, $study->getEditMediaUri());

            return $this->dataverseClient->deleteFile($url);
        } catch (RuntimeException $e) {
            $dataverseNotificationMgr = new DataverseNotificationManager();
            $dataverseNotificationMgr->createNotification($e->getCode());
            error_log($e->getMessage());
        }
        return false;
    }

    public function deleteDraftDataset($study): bool
    {
        try {
            $dataverseServer = $this->dataverseClient->getConfiguration()->getDataverseServer();
            $apiUrl = $dataverseServer . '/api/datasets/:persistentId/versions/:draft?persistentId=' . $study->getPersistentId();

            $response = $this->dataverseClient->deleteDataset($apiUrl);

            if (json_decode($response)->status == 'OK') {
                $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
                return $dataverseStudyDAO->deleteStudy($study);
            }
        } catch (RuntimeException $e) {
            $dataverseNotificationMgr = new DataverseNotificationManager();
            $dataverseNotificationMgr->createNotification($e->getCode());

            error_log($e->getMessage());

            return false;
        }
    }

    public function downloadDatasetFileById(int $fileId, string $filename): array
    {
        $filesDir = Config::getVar('files', 'files_dir');
        $datasetFileDir = tempnam($filesDir, 'datasetFile');
        unlink($datasetFileDir);
        mkdir($datasetFileDir);

        $filePath = $datasetFileDir . DIRECTORY_SEPARATOR . $filename;
        $resource = \GuzzleHttp\Psr7\Utils::tryFopen($filePath, 'w');

        $dataverseServer = $this->dataverseClient->getConfiguration()->getDataverseServer();
        $httpClient = Application::get()->getHttpClient();
        try {
            $response = $httpClient->request(
                'GET',
                $dataverseServer . '/api/access/datafile/' . $fileId,
                [
                    'headers' => [
                        'X-Dataverse-key' => $this->dataverseClient->getConfiguration()->getApiToken()
                    ],
                    'sink' => $resource
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $returnMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $returnMessage = $e->getResponse()->getBody(true) . ' (' .$e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase() . ')';
            }
            return [
                'statusCode' => $e->getResponse()->getStatusCode(),
                'message' => $returnMessage
            ];
        }

        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();
        $fileManager->downloadByPath($filePath);

        $fileManager->rmtree($datasetFileDir);

        return [
            'statusCode' => $response->getStatusCode(),
            'filePath' => $filePath
        ];
    }
}
