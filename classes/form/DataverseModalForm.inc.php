<?php

import('lib.pkp.classes.form.Form');

class DataverseModalForm extends Form {

	private $plugin;

	function DataverseModalForm(Plugin $plugin)
	{
        $this->plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('sendDataset.tpl'));
	}

	function initData(): void
	{
	}

	function readInputData(): void
	{
	}

	function fetch($request, $template = null, $display = false)
	{
	}

	function execute(...$functionArgs)
	{
	}
}
