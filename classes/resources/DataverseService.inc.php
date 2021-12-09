<?php

import('plugins.generic.dataverse.classes.resources.DataverseClient');

define('DATASET_GENRE_ID', 7);

class DataverseService {

    private DataverseClient $dataverseClient;
    private Submission $submission;

    function __construct(DataverseClient $dataverseClient)
	{
        $this->dataverseClient = $dataverseClient;
    }

	function setSubmission(Submission $submission): void
	{
		$this->submission = $submission;
	}

	function hasDataSetComponent(): bool
	{
		foreach($this->submission->getGalleys() as $galley) {
			$galleysFilesGenres[] = $galley->getFile()->getGenreId();
		}
		return in_array(DATASET_GENRE_ID, $galleysFilesGenres);
	}

    function createPackage(): DataversePackageCreator
	{
		$package = new DataversePackageCreator();
		$submissionAdapterCreator = new SubmissionAdapterCreator();
		$datasetFactory = new DatasetFactory();
		$submissionAdapter = $submissionAdapterCreator->createSubmissionAdapter($this->submission);
		$datasetModel = $datasetFactory->build($submissionAdapter);
		$package->loadMetadata($datasetModel);
		$package->createAtomEntry();

		$publicFilesDir = Config::getVar('files', 'files_dir');
		foreach($this->submission->getGalleys() as $galley) {
			$galleyFilePath = $publicFilesDir . DIRECTORY_SEPARATOR  . $galley->getFile()->getLocalizedData('path');
			$package->addFileToPackage($galleyFilePath, $galley->getFile()->getLocalizedData('name'));
		}
		$package->createPackage();

		return $package;
	}

	function depositPackage(): void
	{
		$package = $this->createPackage();

		$study = $this->dataverseClient->depositAtomEntry($package->getAtomEntryPath(), $this->submission->getId());
		if(!is_null($study)) {
			$this->dataverseClient->depositFiles(
				$study->getEditMediaUri(),
				$package->getPackageFilePath(),
				$package->getPackaging(),
				$package->getContentType()
			);
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

	function releaseDataverse(): bool
	{
		return $this->dataverseClient->completeIncompleteDeposit($this->dataverseClient->getConfiguration()->getDataverseReleaseUrl());
	}

	function releaseStudy(): bool
	{
		$dvReleased = $this->dataverseIsReleased();
		if(!$dvReleased) {
			$dvReleased = $this->releaseDataverse();
		}

		if($dvReleased) {
			$dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
			$study = $dataverseStudyDao->getStudyBySubmissionId($this->submission->getId());
			$studyReleased = $this->dataverseClient->completeIncompleteDeposit($study->getEditUri());
			if ($studyReleased) {
				$depositReceipt = $this->dataverseClient->retrieveDepositReceipt($study->getEditUri());
				if (!empty($depositReceipt)) {
					$study->setDataCitation($depositReceipt->sac_dcterms['bibliographicCitation'][0]);
					$dataverseStudyDao->updateStudy($study);
				}		
			}
		}
		return $dvReleased;
	}
}
