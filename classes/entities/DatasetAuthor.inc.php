<?php

class DatasetAuthor extends DataObject
{
    public function __construct(string $name, ?string $affiliation = null, ?array $identifier = null)
    {
        $this->setData('name', $name);
        $this->setData('affiliation', $affiliation);
        $this->setData('identifier', $identifier);
    }

    public function setName(string $name): void
    {
        $this->setData('name', $name);
    }

    public function getName(): string
    {
        return $this->getData('name');
    }

    public function setAffiliation(?string $affiliation): void
    {
        $this->setData('affiliation', $affiliation);
    }

    public function getAffiliation(): ?string
    {
        return $this->getData('affiliation');
    }

    public function setIdentifier(?array $identifier): void
    {
        $this->setData('identifier', $identifier);
    }

    public function getIdentifier(): ?array
    {
        return $this->getData('identifier');
    }
}
