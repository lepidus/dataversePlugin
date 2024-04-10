<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\packagers;

use APP\plugins\generic\dataverse\classes\entities\Dataset;

abstract class DatasetPackager
{
    protected $dataset;

    public function __construct(Dataset $dataset)
    {
        $this->dataset = $dataset;
    }

    abstract public function getPackagePath(): string;

    abstract public function createDatasetPackage(): void;

    abstract public function createFilesPackage(): void;

    abstract public function clear(): void;
}
