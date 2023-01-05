<?php

interface DepositAPIClient
{
    public function newDatasetProvider(Submission $submission): DatasetProvider;
    
    public function depositDataset(DatasetProvider $datasetProvider): DataverseResponse;

    public function depositDatasetFiles(string $persistentId, DatasetProvider $datasetProvider): DataverseResponse;
}