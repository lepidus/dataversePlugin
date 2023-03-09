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
            $contextId = $submission->getContextId();

            import('plugins.generic.dataverse.classes.DataverseConfiguration');
            $configuration = new DataverseConfiguration(
                $this->plugin->getSetting($contextId, 'dataverseUrl'),
                $this->plugin->getSetting($contextId, 'apiToken')
            );

            import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');
            $serviceFactory = new DataverseServiceFactory();
            $service = $serviceFactory->build($configuration);

            $citation = $service->getStudyCitation($study);
            $templateMgr->assign('datasetCitation', $citation);
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitation.tpl'));
        }

        return false;
    }
}
