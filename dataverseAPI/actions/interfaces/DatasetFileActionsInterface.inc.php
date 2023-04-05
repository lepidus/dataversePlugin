<?php

interface DatasetFileActionsInterface
{
    public function getByDatasetId(string $datasetId): array;

    public function add(ResearchDataFile $researchDataFile): void;

    public function delete(string $datasetFileId): void;

    public function download(string $datasetFileId): void;
}
