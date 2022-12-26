<?php

class SubmissionAdapter
{
    private $id;
    private $title;
    private $authors;
    private $description;
    private $keywords;
    private $reference;
    private $subject;

    public function __construct(int $id, string $title, array $authors, array $files, string $description, ?array $keywords, array $reference = array(), ?string $subject = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->authors = $authors;
        $this->files = $files;
        $this->description = $description;
        $this->keywords = $keywords;
        $this->reference = $reference;
        $this->subject = $subject;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getKeywords(): ?array
    {
        return $this->keywords;
    }

    public function getReference(): array
    {
        return $this->reference;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }
}
