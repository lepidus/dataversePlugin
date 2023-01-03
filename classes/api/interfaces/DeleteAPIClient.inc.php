<?php

interface DeleteAPIClient
{
    public function deleteDatasetFile(int $file): DataverseResponse;
}