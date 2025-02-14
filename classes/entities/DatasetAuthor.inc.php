<?php

class DatasetAuthor extends DataObject
{
    public const IDENTIFIER_TYPE_ORCID = 'ORCID';

    public function __construct(string $name, ?string $affiliation, ?string $identifierType, ?string $identifier)
    {
        $this->setData('name', $name);
        $this->setData('affiliation', $affiliation);
        $this->setData('identifierType', $identifierType);
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

    public function setIdentifier(?string $identifier): void
    {
        $this->setData('identifier', $identifier);
    }

    public function getIdentifier(): ?string
    {
        return $this->getData('identifier');
    }

    public function getIdentifierType(): ?string
    {
        return $this->getData('identifierType');
    }
}
