<?php

interface DataAPIClient
{
    public function getDataverseData(): DataverseResponse;

    public function getDatasetData(string $persistentId): DataverseResponse;
}