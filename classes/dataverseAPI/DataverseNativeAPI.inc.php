<?php

import('plugins.generic.dataverse.classes.dataverseAPI.DataverseAPI');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.implementations.NativeAPICollectionOperations');

class DataverseNativeAPI implements DataverseAPI
{
    private $collectionOperations;

    public function __construct()
    {
        $this->collectionOperations = new NativeAPICollectionOperations();
    }

    public function configure(DataverseCredentials $config): void
    {
        $this->collectionOperations->configure($config);
    }

    public function getCollectionOperations(): CollectionOperationsInterface
    {
        return $this->collectionOperations;
    }
}
