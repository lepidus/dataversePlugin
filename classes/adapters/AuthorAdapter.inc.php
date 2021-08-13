<?php

class AuthorAdapter
{
    private $fullName;
    private $affiliation;
    private $email;

    public function __construct(string $fullName, string $affiliation, string $email)
    {
        $this->fullName = $fullName;
        $this->affiliation = $affiliation;
        $this->email = $email;
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
