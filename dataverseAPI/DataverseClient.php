<?php

namespace APP\plugins\generic\dataverse\dataverseAPI;

use GuzzleHttp\Client;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;

class DataverseClient
{
    private ?DataverseConfiguration $configuration;
    private ?int $contextId;
    private ?Client $client;

    public function __construct(
        ?DataverseConfiguration $configuration = null,
        ?int $contextId = null,
        ?Client $client = null
    ) {
        $this->configuration = $configuration;
        $this->contextId = $contextId;
        $this->client = $client;
    }

    public function getDataverseCollectionActions(): DataverseCollectionActions
    {
        return new DataverseCollectionActions($this->configuration, $this->client, $this->contextId);
    }

    public function getDatasetActions(): DatasetActions
    {
        return new DatasetActions($this->configuration, $this->client, $this->contextId);
    }

    public function getDatasetFileActions(): DatasetFileActions
    {
        return new DatasetFileActions($this->configuration, $this->client, $this->contextId);
    }
}
