<?php

Import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DatasetCitationDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('Templates::Preprint::Details', array($this, 'addDatasetCitation'));
    }

    public function addDatasetCitation(string $hookName, array $params): bool
    {
        $templateMgr =& $params[1];
        $output =& $params[2];

        $submission = $templateMgr->getTemplateVars('preprint');
        $contextId = $submission->getContextId();

        $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());

        if (isset($study)) {
            import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
            $dataverseClient = new DataverseClient();

            try {
                $citation = $dataverseClient->getDatasetActions()->getCitation($study->getPersistentId());
                $templateMgr->assign('datasetCitation', $citation);
                $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitation.tpl'));
            } catch (DataverseException $e) {
                error_log('Error getting citation: ' . $e->getMessage());
            }
        }

        return false;
    }
}
