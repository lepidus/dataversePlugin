<?php

class SubmissionFileAdapter
{
    private $id;
    private $genreId;
    private $name;
    private $path;
    private $publishData;
    private $sponsor;

    function __construct(int $id, int $genreId, string $name, string $path, bool $publishData, string $sponsor)
    {
        $this->id = $id;
        $this->genreId = $genreId;
        $this->name = $name;
        $this->path = $path;
        $this->publishData = $publishData;
        $this->sponsor = $sponsor;
    }

    public function getId(): int
    {
        return $this->id;
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