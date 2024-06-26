<?php

namespace APP\plugins\generic\dataverse\classes\factories;

use APP\plugins\generic\dataverse\classes\entities\Dataset;

abstract class DatasetFactory
{
    protected $dataset;

    abstract protected function sanitizeProps(): array;

    final protected function createDataset(): void
    {
        $sanitizedProps = $this->sanitizeProps();

        $dataset = new Dataset();
        $dataset->setAllData($sanitizedProps);
        $this->dataset = $dataset;
    }

    final public function getDataset(): Dataset
    {
        $this->createDataset();

        return $this->dataset;
    }
}
