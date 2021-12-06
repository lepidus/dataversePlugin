<?php

import('plugins.generic.dataverse.classes.DataverseClient');

define('DATASET_GENRE_ID', 7);

class DataverseService {

    private $dataverseClient;
    private $submission;

    function __construct($dataverseClient) {
        $this->dataverseClient = $dataverseClient;
    }

	function setSubmission($submission) {
		$this->submission = $submission;
	}

	function hasDataSetComponent() {
		foreach($this->submission->getGalleys() as $galley) {
			$galleysFilesGenres[] = $galley->getFile()->getGenreId();
		}
		return in_array(DATASET_GENRE_ID, $galleysFilesGenres);
	}

    function createPackage() {
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

	function depositPackage() {
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

	public function dataverseIsReleased() {		
		$depositReceipt = $this->dataverseClient->retrieveDepositReceipt($this->dataverseClient->getConfiguration()->getDataverseDepositUrl());

		$released = false;
		if (!is_null($depositReceipt)) {
			$depositReceiptXml = new SimpleXMLElement($depositReceipt->sac_xml);
			$releasedNodes = $depositReceiptXml->children('http://purl.org/net/sword/terms/state')->dataverseHasBeenReleased;
			if (!empty($releasedNodes) && $releasedNodes[0] == 'true') {
				$released = true;
			}
		}
		return $released;
    }

	function releaseDataverse() {
		return $this->dataverseClient->completeIncompleteDeposit($this->dataverseClient->getConfiguration()->getDataverseReleaseUrl());
	}

	function releaseStudy(){
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
				if (!is_null($depositReceipt)) {
					$study->setDataCitation($depositReceipt->sac_dcterms['bibliographicCitation'][0]);
					$dataverseStudyDao->updateStudy($study);
				}		
			}
			return $studyReleased;
		}
		return $dvReleased;
	}
}
