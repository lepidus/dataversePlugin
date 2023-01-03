<?php

interface EditAPIClient
{
    public function editDataset(DatasetProvider $datasetProvider, string $persistentId): DataverseResponse;
}