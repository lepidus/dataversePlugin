<?php

namespace APP\plugins\generic\dataverse\classes\entities;

use PKP\core\DataObject;

class DatasetIdentifier extends DataObject
{
    public function getPersistentId(): string
    {
        return $this->getData('persistentId');
    }

    public function setPersistentId(string $persistentId): void
    {
        $this->setData('persistentId', $persistentId);
    }
}
