<?php

interface IDataAPIClient
{
    public function getDataverseData(): DataverseResponse;

    public function getDatasetData(string $persistentId): DataverseResponse;
}
