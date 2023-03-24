<?php

abstract class DataverseEntity extends DataObject
{
    abstract public function getProperties(): array;

    final public function validateData(array $data): bool
    {
        $properties = $this->getProperties();

        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $properties)) {
                return false;
            }
        }

        return true;
    }
}
