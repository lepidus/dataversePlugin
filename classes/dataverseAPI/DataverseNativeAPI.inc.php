<?php

import('plugins.generic.dataverse.classes.dataverseAPI.operations.nativeAPI.CollectionOperations');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.nativeAPI.DatasetOperations');

class DataverseNativeAPI
{
    private $collectionOperations;
    private $datasetOperations;

    public function __construct()
    {
        $this->collectionOperations = new CollectionOperations();
        $this->datasetOperations = new DatasetOperations();
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
