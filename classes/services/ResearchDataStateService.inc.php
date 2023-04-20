<?php

define('RESEARCH_DATA_IN_MANUSCRIPT', 'inManuscript');
define('RESEARCH_DATA_REPO_AVAILABLE', 'repoAvailable');
define('RESEARCH_DATA_SUBMISSION_DEPOSIT', 'submissionDeposit');
define('RESEARCH_DATA_ON_DEMAND', 'onDemand');
define('RESEARCH_DATA_PRIVATE', 'private');

class ResearchDataStateService
{
    public function getResearchDataStates(): array
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

        return [
            RESEARCH_DATA_IN_MANUSCRIPT => __('plugins.generic.dataverse.researchDataState.inManuscript'),
            RESEARCH_DATA_REPO_AVAILABLE => __('plugins.generic.dataverse.researchDataState.repoAvailable'),
            RESEARCH_DATA_SUBMISSION_DEPOSIT => __(
                'plugins.generic.dataverse.researchDataState.submissionDeposit',
                $params
            ),
            RESEARCH_DATA_ON_DEMAND => __('plugins.generic.dataverse.researchDataState.onDemand'),
            RESEARCH_DATA_PRIVATE => __('plugins.generic.dataverse.researchDataState.private')
        ];
    }

    public function getSubmissionResearchDataStateDescription(Submission $submission): string
    {
        $researchDataState = $submission->getData('researchDataState');

        switch ($researchDataState) {
            case RESEARCH_DATA_IN_MANUSCRIPT:
                $researchDataStateDescription = __(
                    'plugins.generic.dataverse.researchDataState.inManuscript.description'
                );
                break;
            case RESEARCH_DATA_REPO_AVAILABLE:
                $researchDataStateDescription = __(
                    'plugins.generic.dataverse.researchDataState.repoAvailable.description',
                    ['researchDataUrl' => $submission->getData('researchDataUrl')]
                );
                break;
            case RESEARCH_DATA_ON_DEMAND:
                $researchDataStateDescription = __('plugins.generic.dataverse.researchDataState.onDemand.description');
                break;
            case RESEARCH_DATA_PRIVATE:
                $researchDataStateDescription = __(
                    'plugins.generic.dataverse.researchDataState.private.description',
                    ['researchDataReason' => $submission->getData('researchDataReason')]
                );
                break;
            case RESEARCH_DATA_SUBMISSION_DEPOSIT:
            default:
                $researchDataStateDescription = __('plugins.generic.dataverse.researchData.noResearchData');
                break;
        }

        return $researchDataStateDescription;
    }
}
