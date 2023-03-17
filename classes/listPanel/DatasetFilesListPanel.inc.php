<?php

use PKP\components\listPanels\ListPanel;

class DatasetFilesListPanel extends ListPanel
{
    public $addFileLabel = '';

    public $apiUrl = '';

    public $isLoading = false;

    public $modalTitle = '';

    public function __construct($id, $title, $args = [])
    {
        parent::__construct($id, $title, $args);
    }

    public function getConfig()
    {
        $config = parent::getConfig();

        $config = array_merge(
            $config,
            [
                'addFileLabel' => $this->addFileLabel,
                'apiUrl' => $this->apiUrl,
                'isLoading' => $this->isLoading,
                'modalTitle' => $this->modalTitle,
            ]
        );

        return $config;
    }
}
