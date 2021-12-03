<?php

class SubmissionAdapter
{
    private $title;
    private $authors;
    private $description;
    private $keywords;

    public function __construct(string $title, array $authors, string $description, array $keywords, array $reference = null)
    {
        $this->title = $title;
        $this->authors = $authors;
        $this->description = $description;
        $this->keywords = $keywords;
        $this->reference = $reference;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getAuthors()
    {
        return $this->authors;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getKeywords()
    {
        return $this->keywords;
    }

    public function getReference()
    {
        return $this->reference;
    }

}
