<?php

define('DATASET_GENRE_ID', 7);

class DataverseService {

    private $dataverseClient;
    private $submission;
    private $galleys;

    function __construct($dataverseClient, $submission) {
        $this->dataverseClient = $dataverseClient;
        $this->submission = $submission;
		$this->galleys = $this->submission->getGalleys();
    }

	function hasDataSetComponent(){
		foreach($this->galleys as $galley) {
			$galleysFilesGenres[] = $galley->getFile()->getGenreId();
		}
		return in_array(DATASET_GENRE_ID, $galleysFilesGenres);
	}

    function createPackage() {
		$package = new DataversePackageCreator();
		$submissionAdapterCreator = new SubmissionAdapterCreator();
		$datasetBuilder = new DatasetBuilder();
		$submissionAdapter = $submissionAdapterCreator->createSubmissionAdapter($this->submission);
		$datasetModel = $datasetBuilder->build($submissionAdapter);
		$package->loadMetadata($datasetModel);
		$package->createAtomEntry();

		$publicFilesDir = Config::getVar('files', 'files_dir');
		foreach($this->galleys as $galley) {
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
}
