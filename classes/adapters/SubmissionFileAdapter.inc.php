<?php

class SubmissionFileAdapter
{
    private string $path;
    private string $name;
    private bool $publishData;

    public function __construct(string $path, string $name, bool $publishData)
    {
        $this->path = $path;
        $this->name = $name;
        $this->publishData = $publishData;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPublishData(): bool
    {
        return $this->publishData;
    }
}
