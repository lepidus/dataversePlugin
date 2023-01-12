<?php

class Dataset extends DataObject
{
    public function getTitle(): string
    {
        return $this->getData('title');
    }

    public function setTitle(string $title): void
    {
        $this->setData('title', $title);
    }

    public function getDescription(): string
    {
        return $this->getData('description');
    }

    public function setDescription(string $description): void
    {
        $this->setData('description', $description);
    }

    public function getSubject(): string
    {
        return $this->getData('subject');
    }

    public function setSubject(string $subject): void
    {
        $this->setData('subject', $subject);
    }

    public function getAuthors(): array
    {
        return $this->getData('authors');
    }

    public function setAuthors(array $authors): void
    {
        $this->setData('authors', $authors);
    }

    public function getContacts(): array
    {
        return $this->getData('contacts');
    }

    public function setContacts(array $contacts): void
    {
        $this->setData('contacts', $contacts);
    }

    public function getKeywords(): array
    {
        return $this->getData('keywords');
    }

    public function setKeywords(array $keywords): void
    {
        $this->setData('keywords', $keywords);
    }

    public function getCitation(): string
    {
        return $this->getData('citation');
    }

    public function setCitation(string $citation): void
    {
        $this->setData('citation', $citation);
    }
}
