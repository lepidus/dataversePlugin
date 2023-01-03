<?php

import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');
import('plugins.generic.dataverse.classes.api.interfaces.DatasetProvider');

class NativeAPIDatasetProvider implements DatasetProvider
{
    private $datasetFilePath;
    private $datasetContent;
    private $submission;

    public function __construct(Submission $submission)
    {
        $submissionAdapterCreator = new SubmissionAdapterCreator();
        $this->submission = $submissionAdapterCreator->createSubmissionAdapter($submission);
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

    public function prepareMetadata(array $metadada = []): void
    {
        if (!empty($metadada)) {
            $metadataValues = [];
            foreach ($metadada as $name) {
                $metadataName = $this->attributeToMetadata($name);
                $metadataValues[$metadataName] = $this->submission->getData($name);
            }
        }

        $datasetCreator = new DataverseDatasetDataCreator();
        $this->datasetContent = $datasetCreator->createMetadataFields($metadataValues);
    }

    public function createDataset(): void
    {
        $datasetJson = json_encode($this->datasetContent);
        $this->datasetFilePath = $this->createJsonFile($datasetJson);
    }

    public function getDatasetPath(): string
    {
        return $this->datasetFilePath;
    }
    
    private function createJsonFile(string $jsonContent): string
    {
        $fileDir = tempnam('/tmp', 'datasetMetadata');
        unlink($fileDir);
        mkdir($fileDir);
        
        $fileJsonPath = $fileDir . DIRECTORY_SEPARATOR . 'metadata.json';
        $jsonFile = fopen($fileJsonPath, 'w');
        fwrite($jsonFile, $jsonContent);
        fclose($jsonFile);
        
        return $fileJsonPath;
    }

    private function attributeToMetadata(string $attribute): string
    {
        $attribute = ucfirst($attribute);
        return 'dataset' . $attribute;
    }

}