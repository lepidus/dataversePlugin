<?php

namespace APP\plugins\generic\dataverse\classes\services;

use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;

define('DATA_STATEMENT_TYPE_IN_MANUSCRIPT', 0x000000001);
define('DATA_STATEMENT_TYPE_REPO_AVAILABLE', 0x000000002);
define('DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED', 0x000000003);
define('DATA_STATEMENT_TYPE_ON_DEMAND', 0x000000004);
define('DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE', 0x000000005);

class DataStatementService
{
    public function getDataStatementTypes(): array
    {
        $dataverseName = $this->getDataverseName();
        $types = [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT => __('plugins.generic.dataverse.dataStatement.inManuscript'),
            DATA_STATEMENT_TYPE_REPO_AVAILABLE => __('plugins.generic.dataverse.dataStatement.repoAvailable'),
            DATA_STATEMENT_TYPE_ON_DEMAND => __('plugins.generic.dataverse.dataStatement.onDemand'),
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable')
        ];

        if (!is_null($dataverseName)) {
            $types[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED] = __('plugins.generic.dataverse.dataStatement.submissionDeposit', ['dataverseName' => $dataverseName]);
        }

        return $types;
    }

    public function getDataverseName(): ?string
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
            return $dataverseCollection->getName();
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
            return null;
        }
    }
}
