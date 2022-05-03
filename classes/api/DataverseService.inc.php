<?php

import('plugins.generic.dataverse.classes.api.DataverseClient');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.file.DataverseFile');
import('plugins.generic.dataverse.classes.file.DataverseFileDAO');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');
import('plugins.generic.dataverse.classes.creators.DatasetFactory');

class DataverseService {

    private $dataverseClient;
    private $submission;

    function __construct(DataverseClient $dataverseClient)
	{
        $this->dataverseClient = $dataverseClient;
    }

	public function getClient(): DataverseClient
	{
		return $this->dataverseClient;
	}

	function setSubmission(Submission $submission): void
	{
		$submissionAdapterCreator = new SubmissionAdapterCreator();
		$submissionAdapter = $submissionAdapterCreator->createSubmissionAdapter($submission);
		$this->submission = $submissionAdapter;
	}

	function hasDataSetComponent(): bool
	{
		foreach($this->submission->getFiles() as $file) {
			$filesGenres[] = $file->getGenreId();
		}
		return in_array(DATASET_GENRE_ID, $filesGenres);
	}

	function getDataverseName(): ?string
	{
		$dataverseNotificationMgr = new DataverseNotificationManager();
		try {
			$receipt = $this->dataverseClient->retrieveDepositReceipt($this->dataverseClient->getConfiguration()->getDataverseDepositUrl());
		} catch (DomainException $e) {
			error_log($e->getMessage());
			$dataverseNotificationMgr->sendNotification($e->getCode());
		}

		return $receipt->sac_title;
	}

    function createPackage(): DataversePackageCreator
	{
		$package = new DataversePackageCreator();
		$datasetFactory = new DatasetFactory();
		$datasetModel = $datasetFactory->build($this->submission);
		$package->loadMetadata($datasetModel);
		$package->createAtomEntry();

		$publicFilesDir = Config::getVar('files', 'files_dir');
		foreach($this->submission->getFiles() as $file) {
			if ($file->getPublishData()) {
				$filePath = $publicFilesDir . DIRECTORY_SEPARATOR  . $file->getPath();
				$package->addFileToPackage($filePath, $file->getName());
			}
		}
		$package->createPackage();

		return $package;
	}

	function depositPackage(): void
	{
		$package = $this->createPackage();

		$study = $this->depositStudy($package);
		if(!empty($study)) {
			$this->insertDataverseFilesInDB($study);
		}
	}

	public function depositStudy(DataversePackageCreator $package): ?DataverseStudy
	{
		$dataverseNotificationMgr = new DataverseNotificationManager();
		try {
			$study = $this->dataverseClient->depositAtomEntry($package->getAtomEntryPath(), $this->submission->getId());
			if(!is_null($study)) {
				$this->dataverseClient->depositFiles(
					$study->getEditMediaUri(),
					$package->getPackageFilePath(),
					$package->getPackaging(),
					$package->getContentType()
				);
			}
			$dataverseNotificationMgr->sendNotification(DATAVERSE_PLUGIN_HTTP_STATUS_CREATED);
		} catch (DomainException $e) {
			error_log($e->getMessage());
			$dataverseNotificationMgr->sendNotification($e->getCode());
		}	
		return $study;
	}

	public function insertDataverseFilesInDB(DataverseStudy $study): void
	{
		$statement = $this->dataverseClient->retrieveAtomStatement($study->getStatementUri());
		if(!empty($statement)) {
			foreach ($statement->sac_entries as $entry) {
				$dataverseFileKey = substr($entry->sac_content_source, strrpos($entry->sac_content_source, '/')+1);
				$dataverseFiles[$dataverseFileKey] = $entry->sac_content_source;
			}

			$files = $this->submission->getFiles();
			foreach ($files as $file) {
				$fileKey = str_replace(' ', '_', $file->getName());
				if(array_key_exists($fileKey, $dataverseFiles)) {
					$dataverseFile = new DataverseFile();
					$dataverseFile->setStudyId($study->getId());
					$dataverseFile->setSubmissionId($this->submission->getId());
					$dataverseFile->setSubmissionFileId($file->getId());
					$dataverseFile->setContentUri($dataverseFiles[$fileKey]);
					$dataverseFileDAO = DAORegistry::getDAO('DataverseFileDAO');
					$dataverseFileDAO->insertObject($dataverseFile);
				}
			}
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
		$statement = $this->dataverseClient->retrieveAtomStatement($study->getStatementUri());
		$studyReleased = false;
		if (!empty($statement)) {
			$sac_xml = new SimpleXMLElement($statement->sac_xml);
			foreach ($sac_xml->children()->category as $category) {
				if ($category->attributes()->term == 'latestVersionState') {
					if ($category == 'RELEASED') $studyReleased = true;
					break;
				}
			}
		}
		return $studyReleased;
	}

	function releaseDataverse(): bool
	{
		return $this->dataverseClient->completeIncompleteDeposit($this->dataverseClient->getConfiguration()->getDataverseReleaseUrl());
	}

	function releaseStudy(): bool
	{
		$dataverseNotificationMgr = new DataverseNotificationManager();
		try {
			$dvReleased = $this->dataverseIsReleased();
			if(!$dvReleased) {
				$dvReleased = $this->releaseDataverse();
			}

			if($dvReleased) {
				$dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
				$study = $dataverseStudyDao->getStudyBySubmissionId($this->submission->getId());
				$studyPublished = $this->dataverseClient->completeIncompleteDeposit($study->getEditUri());

				if ($studyPublished) {
					$this->updateStudy($study);
					$dataverseNotificationMgr->sendNotification(DATAVERSE_PLUGIN_HTTP_STATUS_OK);
				}
			}
		} catch (DomainException $e) {
			error_log($e->getMessage());
			$dataverseNotificationMgr->sendNotification($e->getCode());
		}
		
		return $dvReleased;
	}

	function updateStudy(DataverseStudy $study): void
	{
		$studyReleased = $this->studyIsReleased($study);
		while (!$studyReleased) $studyReleased = $this->studyIsReleased($study);
		if ($studyReleased) {
			$depositReceipt = $this->dataverseClient->retrieveDepositReceipt($study->getEditUri());
			if (!empty($depositReceipt)) {
				$study->setDataCitation($depositReceipt->sac_dcterms['bibliographicCitation'][0]);
				$dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
				$dataverseStudyDao->updateStudy($study);
			}
		}
	}

	function getTermsOfUse(): string
	{
		return $this->dataverseClient->getDataverseTermsOfUse();
	}
}
