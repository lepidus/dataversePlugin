<?php

class SubmissionAdapter
{
    private $title;
    private $authors;
    private $description;
    private $keywords;
    private $reference;

    public function __construct(string $title, array $authors, string $description, array $keywords, array $reference = array())
    {
        $this->title = $title;
        $this->authors = $authors;
        $this->description = $description;
        $this->keywords = $keywords;
        $this->reference = $reference;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function getReference(): array
    {
        return $this->reference;
    }

}
