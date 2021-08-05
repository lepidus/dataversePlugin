<?php

class AuthorAdapter
{
    private $fullName;
    private $affiliation;

    public function __construct(string $fullName, string $affiliation)
    {
        $this->fullName = $fullName;
        $this->affiliation = $affiliation;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getAffiliation(): string
    {
        return $this->affiliation;
    }
}
