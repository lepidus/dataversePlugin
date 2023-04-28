<?php

Import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DatasetInformationDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('Templates::Preprint::Details', array($this, 'addDatasetInformation'));
        HookRegistry::register('Templates::Article::Details', array($this, 'addDatasetInformation'));
    }

    public function addDatasetInformation(string $hookName, array $params): bool
    {
        $templateMgr =& $params[1];
        $output =& $params[2];

        $submission = $templateMgr->getTemplateVars('preprint') ?? $templateMgr->getTemplateVars('article');
        $publication = $submission->getCurrentPublication();
        $researchDataState = $publication->getData('researchDataState');
        $contextId = $submission->getContextId();

        $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());

        if (isset($study)) {
            import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
            $dataverseClient = new DataverseClient();

            try {
                $citation = $dataverseClient->getDatasetActions()->getCitation($study->getPersistentId());
                $templateMgr->assign('datasetInfo', $citation);
                $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitation.tpl'));
            } catch (DataverseException $e) {
                error_log('Error getting citation: ' . $e->getMessage());
            }
        } elseif (isset($researchDataState) && $researchDataState != RESEARCH_DATA_SUBMISSION_DEPOSIT) {
            import('plugins.generic.dataverse.classes.services.ResearchDataStateService');
            $researchDataStateService = new ResearchDataStateService();
            $templateMgr->assign(
                'datasetInfo',
                $researchDataStateService->getResearchDataStateDescription($publication)
            );
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitation.tpl'));
        }

        return false;
    }
}
