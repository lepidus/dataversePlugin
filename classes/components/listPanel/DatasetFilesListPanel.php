<?php

namespace APP\plugins\generic\dataverse\classes\components\listPanel;

use PKP\components\listPanels\ListPanel;

class DatasetFilesListPanel extends ListPanel
{
    public $addFileLabel = '';
    public $apiUrl = '';
    public $isLoading = false;
    public $modalTitle = '';
    public $title = '';

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
                'modalTitle' => $this->modalTitle,
                'title' => $this->title,
            ]
        );

        return $config;
    }
}
