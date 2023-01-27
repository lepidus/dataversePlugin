<?php

class DatasetContact extends DataObject
{
    public function __construct(string $name, string $email, ?string $affiliation = null)
    {
        $this->setData('name', $name);
        $this->setData('email', $email);
        $this->setData('affiliation', $affiliation);
    }

    public function setName(string $name): void
    {
        $this->setData('name', $name);
    }

    public function getName(): string
    {
        return $this->getData('name');
    }

    public function setEmail(string $email): void
    {
        $this->setData('email', $email);
    }

    public function getEmail(): string
    {
        return $this->getData('email');
    }

    public function setAffiliation(?string $affiliation): void
    {
        $this->setData('affiliation', $affiliation);
    }

    public function getAffiliation(): ?string
    {
        return $this->getData('affiliation');
    }
}
