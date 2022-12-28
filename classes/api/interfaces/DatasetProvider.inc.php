<?php

interface DatasetProvider
{
    public function getSubmissionFiles(): array;

    public function createDataset(): void;

    public function prepareDatasetFiles(array $files): void;

    public function getDatasetPath(): string;
}
