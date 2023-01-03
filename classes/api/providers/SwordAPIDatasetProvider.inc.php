<?php

import('plugins.generic.dataverse.classes.api.interfaces.DatasetProvider');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');

class SwordAPIDatasetProvider implements DatasetProvider
{
    private $submission;
    private $package;

    public function __construct(Submission $submission)
    {
        $submissionAdapterCreator = new SubmissionAdapterCreator();
        $this->submission = $submissionAdapterCreator->createSubmissionAdapter($submission);
        $this->package = new DataversePackageCreator();
    }

    public function prepareMetadata(array $metadata = []): void
    {
        if (empty($metadata)) {
            $datasetFactory = new DatasetFactory();
            $datasetModel = $datasetFactory->build($this->submission);
            $this->package->loadMetadata($datasetModel);
        }
    }

    public function createDataset(): void
    {
        $this->package->createAtomEntry();
    }

    public function getSubmissionFiles(): array
    {
        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();

        $files = [];
        foreach ($this->submission->getFiles() as $file) {
            $files[] = $temporaryFileManager->getFile(
                $file->getData('fileId'),
                $file->getData('userId')
            );
        }

        return $files;
    }

    public function prepareDatasetFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->package->addFileToPackage(
                $file->getFilePath(),
                $file->getOriginalFileName()
            );
        }
        $this->package->createPackage();
    }

    public function getDatasetPath(): string
    {
        return $this->package->getAtomEntryPath();
    }

    public function getPackage(): DataversePackageCreator
    {
        return $this->package;
    }
}
