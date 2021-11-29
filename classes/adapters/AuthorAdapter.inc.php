<?php

class AuthorAdapter
{
    private $givenName;
    private $familyName;
    private $fullName;
    private $affiliation;
    private $email;

    public function __construct(string $givenName, string $familyName, string $fullName, string $affiliation, string $email)
    {
        $this->givenName = $givenName;
        $this->familyName = $familyName;
        $this->fullName = $fullName;
        $this->affiliation = $affiliation;
        $this->email = $email;
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
        return $this->fullName;
    }

    public function getAffiliation(): string
    {
        return $this->affiliation;
    }

    public function getAuthorEmail(): string
    {
        return $this->email;
    }
}
