<?php

interface DatasetOperationsInterface
{
    public function addFile(string $persistentId, DatasetFile $file): void;
}
