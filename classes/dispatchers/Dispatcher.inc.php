<?php

import('plugins.generic.dataverse.classes.DataverseConfiguration');

class Dispatcher
{
    private $plugin;

    public function __construct(Plugin $plugin)
	{
        $this->plugin = $plugin;
    }

    public function getDataverseConfiguration(int $contextId): DataverseConfiguration {
		return new DataverseConfiguration(
            $this->plugin->getSetting($contextId, 'apiToken'),
            $this->plugin->getSetting($contextId, 'dataverseServer'),
            $this->plugin->getSetting($contextId, 'dataverse')
        );
	}
}
