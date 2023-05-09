<?php

define('DATA_STATEMENT_TYPE_IN_MANUSCRIPT', 0x000000001);
define('DATA_STATEMENT_TYPE_REPO_AVAILABLE', 0x000000002);
define('DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED', 0x000000003);
define('DATA_STATEMENT_TYPE_ON_DEMAND', 0x000000004);
define('DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE', 0x000000005);

class DataStatementService
{
    public function getDataStatementTypes(): array
    {
        return [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT => __('plugins.generic.dataverse.dataStatement.inManuscript'),
            DATA_STATEMENT_TYPE_REPO_AVAILABLE => __('plugins.generic.dataverse.dataStatement.repoAvailable'),
            DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED => $this->getDataverseSubmittedLabel(),
            DATA_STATEMENT_TYPE_ON_DEMAND => __('plugins.generic.dataverse.dataStatement.onDemand'),
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable')
        ];
    }

    private function getDataverseSubmittedLabel(): string
    {
        try {
            import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
            $dataverseClient = new DataverseClient();
            $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
            $params = [
                'dataverseName' => $dataverseCollection->getName(),
            ];
        } catch (DataverseException $e) {
            error_log($e->getMessage());
        }

        return __('plugins.generic.dataverse.dataStatement.submissionDeposit', $params);
    }

    public function getResearchDataStateDescription(Publication $publication): string
    {

        $researchDataState = $publication->getData('researchDataState');

        $statesDescriptions = [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT => __(
                'plugins.generic.dataverse.researchDataState.inManuscript.description'
            ),
            DATA_STATEMENT_TYPE_REPO_AVAILABLE => __(
                'plugins.generic.dataverse.researchDataState.repoAvailable.description',
                ['researchDataUrl' => $publication->getData('researchDataUrl')]
            ),
            DATA_STATEMENT_TYPE_ON_DEMAND => __(
                'plugins.generic.dataverse.researchDataState.onDemand.description'
            ),
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => __(
                'plugins.generic.dataverse.researchDataState.private.description',
                ['researchDataReason' => $publication->getData('researchDataReason')]
            ),
            DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED => __('plugins.generic.dataverse.researchData.noResearchData')
        ];

        $researchDataStateDescription = $statesDescriptions[$researchDataState] ?? __('plugins.generic.dataverse.researchData.noResearchData');

        return $researchDataStateDescription;
    }
}
