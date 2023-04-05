<?php

interface DatasetActionsInterface
{
    public function get(string $persistendId): Dataset;

    public function create(Dataset $dataset): DatasetIndentifier;

    public function update(Dataset $dataset): void;

    public function delete(): void;

    public function publish(): void;
}
