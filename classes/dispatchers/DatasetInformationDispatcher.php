<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;

class DatasetInformationDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('Templates::Preprint::Details', [$this, 'addDatasetInformation']);
        Hook::add('Templates::Article::Details', [$this, 'addDatasetInformation']);
    }

    public function addDatasetInformation(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        $submission = $templateMgr->getTemplateVars('preprint') ?? $templateMgr->getTemplateVars('article');
        $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());

        if (isset($study)) {
            $dataverseClient = new DataverseClient();

            try {
                $citationData = $dataverseClient->getDatasetActions()->getCitation($study->getPersistentId(), null);
                if ($citationData['datasetIsPublished']) {
                    $templateMgr->assign('datasetInfo', $citationData['citation']);
                    $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitation.tpl'));
                }
            } catch (DataverseException $e) {
                error_log('Error getting citation: ' . $e->getMessage());
            }
        }

        return false;
    }
}
