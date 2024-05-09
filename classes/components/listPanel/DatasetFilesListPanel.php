<?php

namespace APP\plugins\generic\dataverse\classes\components\listPanel;

use APP\core\Application;
use PKP\components\listPanels\ListPanel;
use APP\plugins\generic\dataverse\classes\components\forms\DraftDatasetFileForm;

class DatasetFilesListPanel extends ListPanel
{
    public $addFileLabel = '';
    public $datasetFilesApiUrl = '';
    public $isLoading = false;
    public $modalTitle = '';
    public $title = '';
    private $submission;

    public function __construct($id, $title, $submission, $args = [])
    {
        parent::__construct($id, $title, $args);
        $this->submission = $submission;
    }

    public function getConfig()
    {
        $config = parent::getConfig();
        $form = $this->getForm();

        $config = array_merge(
            $config,
            [
                'addFileLabel' => $this->addFileLabel,
                'datasetFilesApiUrl' => $this->datasetFilesApiUrl,
                'modalTitle' => $this->modalTitle,
                'title' => $this->title,
                'form' => $form->getConfig(),
            ]
        );

        return $config;
    }

    private function getForm(): DraftDatasetFileForm
    {
        $request = Application::get()->getRequest();
        $userId = $request->getUser()->getId();
        $addFileUrl = $this->datasetFilesApiUrl . "&userId=$userId";

        return new DraftDatasetFileForm(
            $addFileUrl,
            $request->getContext()
        );
    }
}
