<?php

abstract class DataverseEndpoints
{
    protected $installation;

    public function __construct(DataverseInstallation $installation)
    {
        $this->installation = $installation;
    }

    abstract protected function getAPIBaseUrl(): string;
}
