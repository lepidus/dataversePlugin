<?php 

class DataverseService {

    private $dataverseClient;
    private $submission;
    private $galleysFiles;

    function __construct($dataverseClient, $submission, $galleysFiles) {
        $this->dataverseClient = $dataverseClient;
        $this->submission = $submission;
        $this->galleysFiles = $galleysFiles;
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
		foreach($this->galleysFiles as $galleysFile) {
			$galleysFilePath = $publicFilesDir . DIRECTORY_SEPARATOR  . $galleysFile->getLocalizedData('path');
			$package->addFileToPackage($galleysFilePath, $galleysFile->getLocalizedData('name'));
		}
		$package->createPackage();

		return $package;
	}

	function depositPackage() {
		$package = $this->createPackage();

		$editMediaIri = $this->dataverseClient->depositAtomEntry($package->getAtomEntryPath());

		if(!is_null($editMediaIri)) {
			$this->dataverseClient->depositFiles(
				$editMediaIri,
				$package->getPackageFilePath(),
				$package->getPackaging(),
				$package->getContentType()
			);
		}
	}

}
