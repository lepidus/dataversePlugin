<?php

class AuthorAdapter
{
    private $givenName;
    private $familyName;
    private $affiliation;
    private $email;
    private $orcid;

    public function __construct(string $givenName, string $familyName, string $affiliation, string $email, string $orcid = null)
    {
        $this->givenName = $givenName;
        $this->familyName = $familyName;
        $this->affiliation = $affiliation;
        $this->email = $email;
        $this->orcid = $orcid;
    }

    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function getFamilyName(): string
    {
        return $this->familyName;
    }

    public function getFullName(): string
    {
        return $this->getFamilyName() . ', ' . $this->getGivenName();
    }

    public function getAffiliation(): string
    {
        return $this->affiliation;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getOrcid(): ?string
    {
        return $this->orcid;
    }
}
