<?php

interface IDepositAPIClient
{
    public function getDatasetPackager(Dataset $datataset): DatasetPackager;

    public function depositDataset(DatasetPackager $packager): DataverseResponse;

    public function depositDatasetFiles(string $persistentId, DatasetPackager $packager): DataverseResponse;
}
