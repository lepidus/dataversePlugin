<?php

class DatasetFileData
{
    private $id;
    private $persistentId;
    private $pidURL;
    private $filename;
    private $contentType;
    private $filesize;
    private $description;
    private $storageIdentifier;
    private $rootDataFileId;
    private $md5;
    private $checksum;
    private $creationDate;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->persistentId = $data['persistentId'];
        $this->pidURL = $data['pidURL'];
        $this->filename = $data['filename'];
        $this->contentType = $data['contentType'];
        $this->filesize = $data['filesize'];
        $this->description = $data['description'];
        $this->storageIdentifier = $data['storageIdentifier'];
        $this->rootDataFileId = $data['rootDataFileId'];
        $this->md5 = $data['md5'];
        $this->checksum = $data['checksum'];
        $this->creationDate = $data['creationDate'];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPersistentId(): string
    {
        return $this->persistentId;
    }

    public function getPidURL(): string
    {
        return $this->pidURL;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getFilesize(): int
    {
        return $this->filesize;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStorageIdentifier(): string
    {
        return $this->storageIdentifier;
    }

    public function getRootDataFileId(): int
    {
        return $this->rootDataFileId;
    }

    public function getMd5(): string
    {
        return $this->md5;
    }

    public function getChecksum(): array
    {
        return $this->checksum;
    }

    public function getCreationDate(): string
    {
        return $this->creationDate;
    }
}
