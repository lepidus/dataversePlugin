<?php

interface UpdateAPIClient
{
    public function updateDataset(DatasetProvider $datasetProvider, string $persistentId): DataverseResponse;
}