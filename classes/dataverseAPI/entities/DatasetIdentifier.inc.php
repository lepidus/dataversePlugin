<?php

import('plugins.generic.dataverse.classes.dataverseAPI.entities.DataverseEntity');

class DatasetIdentifier extends DataverseEntity
{
    public function getProperties(): array
    {
        return [
            'id' => 'int',
            'persistentId' => 'string',
        ];
    }

    public function getPersistentId(): string
    {
        return $this->getData('persistentId');
    }

    public function setPersistentId(string $persistentId): void
    {
        $this->setData('persistentId', $persistentId);
    }
}
