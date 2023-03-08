<?php

interface IUpdateAPIClient
{
    public function updateDataset(string $persistentId, DatasetPackager $packager): DataverseResponse;
}
