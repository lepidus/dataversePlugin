<?php

use PKP\components\listPanels\ListPanel;

class DatasetFilesListPanel extends ListPanel
{
	public $apiUrl = '';

    function __construct($id, $title, $args = [])
	{
		parent::__construct($id, $title, $args);
	}

	public function getConfig()
	{
		$config = parent::getConfig();

		$config = array_merge(
			$config,
			[
				'apiUrl' => $this->apiUrl,
			]
		);

		return $config;
	}
}