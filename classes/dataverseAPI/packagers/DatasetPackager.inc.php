<?php

abstract class DatasetPackager
{
    protected $dataset;

    public function __construct(Dataset $dataset)
    {
        $this->dataset = $dataset;
    }

    abstract public function createDatasetPackage(): void;

    abstract public function createFilesPackage(): void;

    abstract public function getPackagePath(): string;
}
