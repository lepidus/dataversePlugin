<?php

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
