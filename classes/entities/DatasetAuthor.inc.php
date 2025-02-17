<?php

class DatasetAuthor extends DataObject
{
    public const IDENTIFIER_SCHEME_ORCID = 'ORCID';

    public function __construct(string $name, ?string $affiliation, ?string $identifierScheme, ?string $identifier)
    {
        $this->setData('name', $name);
        $this->setData('affiliation', $affiliation);
        $this->setData('identifierScheme', $identifierScheme);
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

    public function getIdentifierScheme(): ?string
    {
        return $this->getData('identifierScheme');
    }
}
