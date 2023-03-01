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
        $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());

        if (isset($study)) {
            try {
                $contentId = $submission->getContextId();

                import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
                $client = new NativeAPIClient($contentId);

                import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');
                $service = new DataAPIService($client);
                $dataset = $service->getDataset($study->getPersistentId());

                $templateMgr->assign('datasetCitation', $dataset->getCitation());
                $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitation.tpl'));
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        return false;
    }
}
