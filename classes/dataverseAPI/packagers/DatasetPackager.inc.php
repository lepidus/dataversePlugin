<?php

abstract class DatasetPackager
{
    protected $dataset;

    public function __construct(Dataset $dataset)
    {
        $this->dataset = $dataset;
    }

    abstract public function createPackage(): void;

    abstract public function getPackagePath(): string;
}
