<?php

interface DatasetActionsInterface
{
    public function get(string $persistendId): Dataset;

    public function create(Dataset $dataset): DatasetIdentifier;

    public function update(Dataset $dataset): void;

    public function delete(string $persistendId): void;

    public function publish(string $persistendId): void;
}
