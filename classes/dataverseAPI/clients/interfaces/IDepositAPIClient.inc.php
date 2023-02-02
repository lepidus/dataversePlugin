<?php

interface IDepositAPIClient
{
    public function getPackager(Dataset $datataset): DatasetPackager;

    public function depositDataset(DatasetPackager $packager): DataverseResponse;

    public function depositDatasetFiles(string $persistentId, DatasetPackager $packager): DatasetResponse;
}
