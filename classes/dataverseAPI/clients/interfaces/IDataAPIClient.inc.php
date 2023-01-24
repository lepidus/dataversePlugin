<?php

interface IDataAPIClient
{
    public function getDataverseData(): array;

    public function getDatasetData(string $persistentId): array;
}
