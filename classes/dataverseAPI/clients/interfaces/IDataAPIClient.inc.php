<?php

interface IDataAPIClient
{
    public function getDatasetFactory(DataverseResponse $response): DatasetFactory;

    public function getDataverseServerData(): DataverseResponse;

    public function getDataverseCollectionData(): DataverseResponse;

    public function getDatasetData(string $persistentId): DataverseResponse;

    public function getDatasetCitation(string $persistentId): DataverseResponse;
}
