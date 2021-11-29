<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');

final class AuthorAdapterTest extends PKPTestCase
{
    private $author;
    private $givenName = "Atila";
    private $familyName = "Iamarino";
    private $fullName = "Atila Iamarino";
    private $affiliation = "Universidade de São Paulo";
    private $email = "atila@usp.edu.br";

    public function setUp(): void
    {
        $this->author = new AuthorAdapter($this->givenName, $this->familyName, $this->fullName, $this->affiliation, $this->email);
    }

    public function testHasFullName(): void
    {
        $this->assertEquals($this->fullName, $this->author->getFullName());
    }

    public function testHasAffiliation(): void
    {
        $this->assertEquals($this->affiliation, $this->author->getAffiliation());
    }

    public function testHasEmail(): void
    {
        $this->assertEquals($this->email, $this->author->getAuthorEmail());
    }
}
