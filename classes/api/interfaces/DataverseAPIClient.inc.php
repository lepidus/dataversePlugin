<?php

interface DataverseAPIClient
{
    public function getDatasetProvider(Submission $submission): DatasetProvider;

    public function getDataverseData(): array;

    public function depositDataset(DatasetProvider $datasetProvider): array;

    public function depositDatasetFiles(string $persistentId, DatasetProvider $datasetProvider): array;

    public function getDatasetData(string $persistentId): array;

    public function publishDataverse(): array;

    public function publishDataset(string $persistentId): array;

    public function deleteDatasetFile(int $file): array;
}
