<?php

interface DatasetProvider
{
    public function prepareMetadata(array $metadata = []): void;

    public function createDataset(): void;

    public function getDatasetPath(): string;
}
