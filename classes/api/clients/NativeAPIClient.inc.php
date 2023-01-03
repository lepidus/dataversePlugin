<?php

class NativeAPIClient
{
    private $configuration;
    private $endpoints;

    public function __construct(int $contextId)
    {
        $this->configuration = new NewDataverseConfiguration($contextId);
        $this->endpoints = new NativeAPIEndpoints($this->configuration->getDataverseServer());
    }

    
}