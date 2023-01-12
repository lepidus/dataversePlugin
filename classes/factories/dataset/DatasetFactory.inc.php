<?php

abstract class DatasetFactory
{
    protected $dataset;

    abstract protected function createDataset(): void;

    public function getDataset(): Dataset
    {
        $this->createDataset();

        return $this->dataset;
    }
}
