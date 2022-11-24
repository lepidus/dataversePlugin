<?php

use PKP\components\listPanels\ListPanel;

class DatasetFilesListPanel extends ListPanel
{
	public $apiUrl = '';

	public $isLoading = false;

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
				'isLoading' => $this->isLoading,
			]
		);

		return $config;
	}
}