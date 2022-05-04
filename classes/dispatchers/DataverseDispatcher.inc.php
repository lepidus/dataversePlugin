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
            $this->plugin->getSetting($contextId, 'dataverseUrl'),
            $this->plugin->getSetting($contextId, 'apiToken')
        );
	}

    public function getDataverseService(): DataverseService
    {
        $serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($this->getDataverseConfiguration(), $this->plugin);
        return $service;
    }
}
