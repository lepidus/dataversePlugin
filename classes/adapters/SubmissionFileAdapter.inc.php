<?php

define('DATASET_GENRE_ID', 7);

class SubmissionFileAdapter
{
    private $genreId;
    private $publishData;
    private $sponsor;

    function __construct(int $genreId, bool $publishData, string $sponsor)
    {
        $this->genreId = $genreId;
        $this->publishData = $publishData;
        $this->sponsor = $sponsor;
    }

    public function getGenreId(): int
    {
        return $this->genreId;
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