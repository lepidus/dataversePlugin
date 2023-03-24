<?php

import('plugins.generic.dataverse.classes.entities.DatasetFile');

interface DatasetOperationsInterface
{
    public function addFile(string $persistentId, DatasetFile $file): DatasetFile;
}
