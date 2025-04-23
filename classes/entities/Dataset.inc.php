<?php

class Dataset extends DataObject
{
    public const VERSION_STATE_DRAFT = 'DRAFT';
    public const VERSION_STATE_RELEASED = 'RELEASED';

    public function getPersistentId(): ?string
    {
        return $this->getData('persistentId');
    }

    public function setPersistentId(string $persistentId): void
    {
        $this->setData('persistentId', $persistentId);
    }

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

    public function getLicense(): ?string
    {
        return $this->getData('license');
    }

    public function setLicense(string $license): void
    {
        $this->setData('license', $license);
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

    public function getRelatedPublication(): ?DatasetRelatedPublication
    {
        return $this->getData('relatedPublication');
    }

    public function setRelatedPublication(DatasetRelatedPublication $relatedPublication): void
    {
        $this->setData('relatedPublication', $relatedPublication);
    }

    public function getFiles(): ?array
    {
        return $this->getData('files');
    }

    public function setFiles(?array $files): void
    {
        $this->setData('files', $files);
    }

    public function getVersionState(): ?string
    {
        return $this->getData('versionState');
    }

    public function setVersionState(string $versionState): void
    {
        $this->setData('versionState', $versionState);
    }

    public function isPublished(): bool
    {
        return $this->getVersionState() === self::VERSION_STATE_RELEASED;
    }
}
