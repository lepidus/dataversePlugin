<?php

class DatasetIdentifier
{
    private $id;
    private $persistentId;

    public function __construct(string $id, string $persistentId)
    {
        $this->id = $id;
        $this->persistentId = $persistentId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPersistentId(): string
    {
        return $this->persistentId;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setPersistentId(string $persistentId): void
    {
        $this->persistentId = $persistentId;
    }
}
