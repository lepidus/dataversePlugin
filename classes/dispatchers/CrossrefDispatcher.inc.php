<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.CrossrefXmlEditor');

class CrossrefDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('articlecrossrefxmlfilter::execute', [$this, 'addDatasetRelationToCrossrefExport']);
        HookRegistry::register('preprintcrossrefxmlfilter::execute', [$this, 'addDatasetRelationToCrossrefExport']);
    }

    public function addDatasetRelationToCrossrefExport(string $hookName, array $params)
    {
        $preliminaryOutput = &$params[0];

        $crossrefXmlEditor = new CrossrefXmlEditor();
        $preliminaryOutput = $crossrefXmlEditor->addDatasetRelationToDepositXml($preliminaryOutput);

        return false;
    }
}
