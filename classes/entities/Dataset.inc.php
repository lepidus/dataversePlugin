<?php

class Dataset extends DataObject
{
    public function getTitle(): ?string
    {
        return $this->getData('title');
    }

    public function setTitle(string $title): void
    {
        $this->setData('title', $title);
    }

    public function getDescription(): ?string
    {
        return $this->getData('description');
    }

    public function setDescription(string $description): void
    {
        $this->setData('description', $description);
    }

    public function getSubject(): ?string
    {
        return $this->getData('subject');
    }

    public function setSubject(string $subject): void
    {
        $this->setData('subject', $subject);
    }

    public function getAuthors(): ?array
    {
        return $this->getData('authors');
    }

    public function setAuthors(array $authors): void
    {
        $this->setData('authors', $authors);
    }

    public function getContact(): ?DatasetContact
    {
        return $this->getData('contact');
    }

    public function setContact(DatasetContact $contact): void
    {
        $this->setData('contact', $contact);
    }

    public function getDepositor(): ?string
    {
        return $this->getData('depositor');
    }

    public function setDepositor(string $depositor): void
    {
        $this->setData('depositor', $depositor);
    }

    public function getKeywords(): ?array
    {
        return $this->getData('keywords');
    }

    public function setKeywords(array $keywords): void
    {
        $this->setData('keywords', $keywords);
    }

    public function getPubCitation(): ?string
    {
        return $this->getData('pubCitation');
    }

    public function setPubCitation(string $pubCitation): void
    {
        $this->setData('pubCitation', $pubCitation);
    }

    public function getFiles(): ?array
    {
        return $this->getData('files');
    }

    public function setFiles(array $files): void
    {
        $this->setData('files', $files);
    }
}
