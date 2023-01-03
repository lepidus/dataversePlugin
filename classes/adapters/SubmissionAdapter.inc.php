<?php

class SubmissionAdapter extends DataObject
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
        $this->setData('id', $id);
        $this->setData('title', $title);
        $this->setData('authors', $authors);
        $this->setData('files', $files);
        $this->setData('description', $description);
        $this->setData('keywords', $keywords);
        $this->setData('reference', $reference);
        $this->setData('subject', $subject);
    }

    public function getId(): int
    {
        return $this->getData('id');
    }

    public function getTitle(): string
    {
        return $this->getData('title');
    }

    public function getAuthors(): array
    {
        return $this->getData('authors');
    }

    public function getFiles(): array
    {
        return $this->getData('files');
    }

    public function getDescription(): string
    {
        return $this->getData('description');
    }

    public function getKeywords(): ?array
    {
        return $this->getData('keywords');
    }

    public function getReference(): array
    {
        return $this->getData('reference');
    }

    public function getSubject(): string
    {
        return $this->getData('subject');
    }
}
