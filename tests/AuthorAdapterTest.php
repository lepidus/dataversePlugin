<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');

final class AuthorAdapterTest extends PKPTestCase
{
    private $author;
    private $fullName = "Atila Iamarino";
    private $affiliation = "Universidade de São Paulo";

    public function setUp(): void
    {
        $this->author = new AuthorAdapter($this->fullName, $this->affiliation);
    }

    public function testHasFullName(): void
    {
        $this->assertEquals($this->fullName, $this->author->getFullName());
    }

    public function testHasAffiliation(): void
    {
        $this->assertEquals($this->affiliation, $this->author->getAffiliation());
    }
}
