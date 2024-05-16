<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces;

use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetIdentifier;

interface DatasetActionsInterface
{
    public function get(string $persistendId): Dataset;

    public function create(Dataset $dataset): DatasetIdentifier;

    public function update(Dataset $dataset): void;

    public function delete(string $persistendId): void;

    public function publish(string $persistendId): void;
}
