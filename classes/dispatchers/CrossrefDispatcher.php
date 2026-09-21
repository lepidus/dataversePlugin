<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use DOMDocument;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\CrossrefXmlEditor;
use APP\plugins\generic\dataverse\classes\DataverseDAO;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;

class CrossrefDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('articlecrossrefxmlfilter::execute', [$this, 'addDatasetRelationToCrossrefExport']);
        Hook::add('preprintcrossrefxmlfilter::execute', [$this, 'addDatasetRelationToCrossrefExport']);
    }

    public function addDatasetRelationToCrossrefExport(string $hookName, array $params)
    {
        $preliminaryOutput = &$params[0];

        $contextId = $this->getDepositContextId($preliminaryOutput);
        if (is_null($contextId)) {
            return Hook::CONTINUE;
        }

        $configurationDAO = DAORegistry::getDAO('DataverseConfigurationDAO');
        if (!$this->plugin->getEnabled($contextId) || !$configurationDAO->hasConfiguration($contextId)) {
            return Hook::CONTINUE;
        }

        $crossrefXmlEditor = $this->createXmlEditor($configurationDAO->get($contextId), $contextId);
        $preliminaryOutput = $crossrefXmlEditor->addDatasetRelationToDepositXml($preliminaryOutput, $contextId);

        return Hook::CONTINUE;
    }

    protected function createXmlEditor(DataverseConfiguration $configuration, int $contextId): CrossrefXmlEditor
    {
        return new CrossrefXmlEditor(new DatasetActions($configuration, null, $contextId));
    }

    private function getDepositContextId(DOMDocument $depositXml): ?int
    {
        $dataverseDao = new DataverseDAO();

        foreach ($depositXml->getElementsByTagName('doi') as $doiNode) {
            $contextId = $dataverseDao->getContextIdByDoi($doiNode->nodeValue);

            if (!is_null($contextId)) {
                return $contextId;
            }
        }

        return null;
    }
}
