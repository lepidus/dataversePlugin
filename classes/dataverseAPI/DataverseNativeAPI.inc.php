<?php

import('plugins.generic.dataverse.classes.dataverseAPI.DataverseAPI');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.implementations.NativeAPICollectionOperations');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.implementations.NativeAPIDatasetOperations');

class DataverseNativeAPI implements DataverseAPI
{
    private $collectionOperations;
    private $datasetOperations;

    public function __construct()
    {
        $this->collectionOperations = new NativeAPICollectionOperations();
        $this->datasetOperations = new NativeAPIDatasetOperations();
    }

    public function configure(DataverseCredentials $config): void
    {
        $this->collectionOperations->configure($config);
        $this->datasetOperations->configure($config);
    }

    public function getCollectionOperations(): CollectionOperationsInterface
    {
        return $this->collectionOperations;
    }

    public function getDatasetOperations(): DatasetOperationsInterface
    {
        return $this->datasetOperations;
    }
}
