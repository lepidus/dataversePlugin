<?php

interface PublishAPIClient
{
    public function publishDataverse(): DataverseResponse;

    public function publishDataset(string $persistentId): DataverseResponse;
}