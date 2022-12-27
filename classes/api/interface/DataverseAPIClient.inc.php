<?php

interface DataverseAPIClient
{
    public function getDataverseData(): array;

    public function depositDataset(DatasetProvider $datasetProvider): array;

    public function addFilesToDataset(DataverseStudy $study, DatasetProvider $datasetProvider): array;

    public function getDatasetData(DataverseStudy $study): array;

    public function publishDataverse(): array;

    public function publishDataset(DataverseStudy $study): array;

    public function deleteDatasetFile(): array;

    public function editDataset(): array;
}
