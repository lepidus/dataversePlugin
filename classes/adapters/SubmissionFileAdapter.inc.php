<?php

define('DATASET_GENRE_ID', 7);

class SubmissionFileAdapter
{
    private $genreId;
    private $name;
    private $path;
    private $publishData;
    private $sponsor;

    function __construct(int $genreId, string $name, string $path, bool $publishData, string $sponsor)
    {
        $this->genreId = $genreId;
        $this->name = $name;
        $this->path = $path;
        $this->publishData = $publishData;
        $this->sponsor = $sponsor;
    }

    public function getGenreId(): int
    {
        return $this->genreId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPublishData(): bool
    {
        return $this->publishData;
    }

    public function getSponsor(): string
    {
        return $this->sponsor;
    }
}

?>