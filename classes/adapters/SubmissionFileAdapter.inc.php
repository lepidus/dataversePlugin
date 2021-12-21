<?php

class SubmissionFileAdapter
{
    private string $path;
    private string $name;
    private int $genreId;
    private bool $publishData;

    public function __construct(string $path, string $name, int $genreId, bool $publishData)
    {
        $this->path = $path;
        $this->name = $name;
        $this->genreId = $genreId;
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

    public function getGenreId(): bool
    {
        return $this->genreId;
    }

    public function getPublishData(): bool
    {
        return $this->publishData;
    }
}
