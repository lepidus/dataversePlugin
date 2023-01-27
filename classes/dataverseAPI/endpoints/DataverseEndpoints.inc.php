<?php

abstract class DataverseEndpoints
{
    protected $server;

    public function __construct(DataverseServer $server)
    {
        $this->server = $server;
    }

    abstract protected function getAPIBaseUrl(): string;
}
