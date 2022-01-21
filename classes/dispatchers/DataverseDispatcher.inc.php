<?php

import('plugins.generic.dataverse.classes.DataverseConfiguration');

class DataverseDispatcher
{
    var $plugin;

    public function __construct(Plugin $plugin)
	{
        $this->plugin = $plugin;
    }

    public function getDataverseConfiguration(): DataverseConfiguration
    {
        $context = $this->plugin->getRequest()->getContext();
        $contextId = $context->getId();

		return new DataverseConfiguration(
            $this->plugin->getSetting($contextId, 'apiToken'),
            $this->plugin->getSetting($contextId, 'dataverseServer'),
            $this->plugin->getSetting($contextId, 'dataverse')
        );
	}
}
