<?php

namespace APP\plugins\generic\dataverse\dataverseAPI;

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;

class DataverseClient
{
    private ?DataverseConfiguration $configuration;
    private ?int $contextId;

    public function __construct(?DataverseConfiguration $configuration = null, ?int $contextId = null)
    {
        $this->configuration = $configuration;
        $this->contextId = $contextId;
    }

    public function getDataverseCollectionActions(): DataverseCollectionActions
    {
        return new DataverseCollectionActions($this->configuration, null, $this->contextId);
    }

    public function getDatasetActions(): DatasetActions
    {
        return new DatasetActions($this->configuration, null, $this->contextId);
    }

    public function getDatasetFileActions(): DatasetFileActions
    {
        return new DatasetFileActions($this->configuration, null, $this->contextId);
    }
}
