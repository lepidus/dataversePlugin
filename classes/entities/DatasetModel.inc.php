<?php

class DatasetModel extends DataObject
{
    public function __construct(string $title, string $description, array $authors, string $subject)
    {
        $this->setData('title', $title);
        $this->setData('description', $description);
        $this->setData('authors', $authors);
        $this->setData('subject', $subject);
    }

    public function setTitle($title): void
    {
        $this->setData('title', $title);
    }

    public function getTitle(): string
    {
        return $this->getData('title');
    }

    public function setDescription($description): void
    {
        $this->setData('description', $description);
    }

    public function getDescription(): string
    {
        return $this->getData('description');
    }

    public function setAuthors($authors): void
    {
        $this->setData('authors', $authors);
    }

    public function getAuthors(): array
    {
        return $this->getData('authors');
    }

    public function setSubject($subject): void
    {
        $this->setData('subject', $subject);
    }

    public function getSubject(): string
    {
        return $this->getData('subject');
    }
}
