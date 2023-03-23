<?php

import('plugins.generic.dataverse.classes.dataverseAPI.operations.interfaces.CollectionOperationsInterface');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.interfaces.DatasetOperationsInterface');

interface DataverseAPI
{
    public function configure(DataverseCredentials $config): void;

    public function getCollectionOperations(): CollectionOperationsInterface;

    public function getDatasetOperations(): DatasetOperationsInterface;
}
