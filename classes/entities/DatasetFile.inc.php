<?php

class DatasetFile extends DataObject
{
    public function getName(): string
    {
        return $this->getData('name');
    }

    public function setName(string $name): void
    {
        $this->setData('name', $name);
    }

    public function getOriginalFileName(): string
    {
        return $this->getData('originalFileName');
    }

    public function setOriginalFileName(string $originalFileName): void
    {
        $this->setData('originalFileName', $originalFileName);
    }

    public function getPath(): string
    {
        return $this->getData('path');
    }

    public function setPath(string $path): void
    {
        $this->setData('path', $path);
    }
}
