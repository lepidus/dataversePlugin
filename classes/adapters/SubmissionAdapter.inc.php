<?php

class SubmissionAdapter extends DataObject
{
    public function setRequiredData(
        int $id,
        string $title,
        string $abstract,
        string $subject,
        ?array $keywords,
        string $citation,
        ?array $contact,
        array $authors,
        array $files
    ): void {
        $this->setId($id);
        $this->setTitle($title);
        $this->setAbstract($abstract);
        $this->setSubject($subject);
        $this->setKeywords($keywords);
        $this->setCitation($citation);
        $this->setContact($contact);
        $this->setAuthors($authors);
        $this->setFiles($files);
    }

    public function getTitle(): string
    {
        return $this->getData('title');
    }

    public function setTitle(string $title): void
    {
        $this->setData('title', $title);
    }

    public function getAbstract(): string
    {
        return $this->getData('abstract');
    }

    public function setAbstract(string $abstract): void
    {
        $this->setData('abstract', $abstract);
    }

    public function getSubject(): string
    {
        return $this->getData('subject');
    }

    public function setSubject(string $subject): void
    {
        $this->setData('subject', $subject);
    }

    public function getKeywords(): array
    {
        return $this->getData('keywords');
    }

    public function setKeywords(?array $keywords): void
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

    public function getContact(): array
    {
        return $this->getData('contact');
    }

    public function setContact(?array $contact): void
    {
        $this->setData('contact', $contact);
    }

    public function getAuthors(): array
    {
        return $this->getData('authors');
    }

    public function setAuthors(array $authors): void
    {
        $this->setData('authors', $authors);
    }

    public function getFiles(): array
    {
        return $this->getData('files');
    }

    public function setFiles(array $files): void
    {
        $this->setData('files', $files);
    }
}
