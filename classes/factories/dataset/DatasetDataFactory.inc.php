<?php

abstract class DatasetDataFactory
{
    protected $dataset;

    abstract protected function createDatasetData(): void;

    public function getDatasetData(): DatasetData
    {
        $this->createDatasetData();

        return $this->dataset;
    }
}
