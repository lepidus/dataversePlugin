<?php

namespace APP\plugins\generic\dataverse\classes\services;

use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;

class DataStatementService
{
    public const DATA_STATEMENT_TYPE_IN_MANUSCRIPT = 0x000000001;
    public const DATA_STATEMENT_TYPE_REPO_AVAILABLE = 0x000000002;
    public const DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED = 0x000000003;
    public const DATA_STATEMENT_TYPE_ON_DEMAND = 0x000000004;
    public const DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE = 0x000000005;

    private $dataverseName;

    public function getDataStatementTypes(): array
    {
        $dataverseName = $this->getDataverseName();
        $types = [
            self::DATA_STATEMENT_TYPE_IN_MANUSCRIPT => __('plugins.generic.dataverse.dataStatement.inManuscript'),
            self::DATA_STATEMENT_TYPE_REPO_AVAILABLE => __('plugins.generic.dataverse.dataStatement.repoAvailable'),
            self::DATA_STATEMENT_TYPE_ON_DEMAND => __('plugins.generic.dataverse.dataStatement.onDemand'),
            self::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable')
        ];

        if (!is_null($dataverseName)) {
            $types[self::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED] = __('plugins.generic.dataverse.dataStatement.submissionDeposit', ['dataverseName' => $dataverseName]);
        }

        return $types;
    }

    public function getConstantsForTemplates(): array
    {
        return [
            'DATA_STATEMENT_TYPE_IN_MANUSCRIPT' => self::DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            'DATA_STATEMENT_TYPE_REPO_AVAILABLE' => self::DATA_STATEMENT_TYPE_REPO_AVAILABLE,
            'DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED' => self::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED,
            'DATA_STATEMENT_TYPE_ON_DEMAND' => self::DATA_STATEMENT_TYPE_ON_DEMAND,
            'DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE' => self::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE,
        ];
    }

    public function getDataverseName(): ?string
    {
        if ($this->dataverseName) {
            return $this->dataverseName;
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
            $this->dataverseName = $dataverseCollection->getName();
            
            return $this->dataverseName;
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
            return null;
        }
    }
}
