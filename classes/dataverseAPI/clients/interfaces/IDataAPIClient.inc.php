<?php

interface IDataAPIClient
{
    public function getDatasetFactory(DataverseResponse $response): DatasetFactory;

    public function getDataverseData(): DataverseResponse;

    public function getDatasetData(string $persistentId): DataverseResponse;
}
