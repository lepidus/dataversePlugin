<?php

import('plugins.generic.dataverse.classes.dataverseAPI.operations.interfaces.CollectionOperationsInterface');

interface DataverseAPI
{
    public function configure(DataverseCredentials $config): void;

    public function getCollectionOperations(): CollectionOperationsInterface;
}
