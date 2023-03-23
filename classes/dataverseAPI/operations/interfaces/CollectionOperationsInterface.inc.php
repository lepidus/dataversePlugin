<?php

import('plugins.generic.dataverse.classes.entities.DatasetIdentifier');

interface CollectionOperationsInterface
{
    public function createDataset(string $datasetPackagePath): DatasetIdentifier;
}
