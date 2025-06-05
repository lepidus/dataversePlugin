<?php

define('DATA_STATEMENT_TYPE_IN_MANUSCRIPT', 0x000000001);
define('DATA_STATEMENT_TYPE_REPO_AVAILABLE', 0x000000002);
define('DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED', 0x000000003);
define('DATA_STATEMENT_TYPE_ON_DEMAND', 0x000000004);
define('DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE', 0x000000005);

class DataStatementService
{
    private $dataverseName;

    public function getDataStatementTypes($includeSubmittedType = true): array
    {
        $types = [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT => __('plugins.generic.dataverse.dataStatement.inManuscript'),
            DATA_STATEMENT_TYPE_REPO_AVAILABLE => __('plugins.generic.dataverse.dataStatement.repoAvailable'),
            DATA_STATEMENT_TYPE_ON_DEMAND => __('plugins.generic.dataverse.dataStatement.onDemand'),
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable')
        ];

        if ($includeSubmittedType) {
            $dataverseUrl = $this->getDataverseUrl();
            $this->getDataverseName();

            if (!is_null($this->dataverseName)) {
                $types[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED] = __(
                    'plugins.generic.dataverse.dataStatement.submissionDeposit',
                    ['dataverseName' => $this->dataverseName, 'dataverseUrl' => $dataverseUrl]
                );
            }
        }

        return $types;
    }

    private function getDataverseUrl(): string
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);

        return $configuration->getDataverseUrl();
    }

    public function getDataverseName(): ?string
    {
        if ($this->dataverseName) {
            return $this->dataverseName;
        }

        try {
            import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
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
