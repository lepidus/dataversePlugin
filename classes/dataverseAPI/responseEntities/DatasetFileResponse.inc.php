<?php

class DatasetFileResponse
{
    private $description;
    private $label;
    private $restricted;
    private $version;
    private $datasetVersionId;
    private $dataFile;

    public function __construct(array $data)
    {
        $this->description = $data['description'];
        $this->label = $data['label'];
        $this->restricted = $data['restricted'];
        $this->version = $data['version'];
        $this->datasetVersionId = $data['datasetVersionId'];
        $this->dataFile = new DatasetFileData($data['dataFile']);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRestricted(): bool
    {
        return $this->restricted;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getDatasetVersionId(): int
    {
        return $this->datasetVersionId;
    }

    public function getDataFile(): DatasetFileData
    {
        return $this->dataFile;
    }
}
