<?php

import('plugins.generic.dataverse.classes.dataverseAPI.entities.DatasetIdentifier');

interface CollectionOperationsInterface
{
    public function createDataset(string $datasetPackagePath): DatasetIdentifier;
}
