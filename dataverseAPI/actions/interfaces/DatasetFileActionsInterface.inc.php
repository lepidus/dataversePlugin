<?php

interface DatasetFileActionsInterface
{
    public function getByDatasetId(string $persistentId): array;

    public function add(string $persistentId, string $filename, string $filePath): void;

    public function delete(int $datasetFileId): void;

    public function download(int $datasetFileId, string $filename): void;
}
