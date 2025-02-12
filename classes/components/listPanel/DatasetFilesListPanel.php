<?php

namespace APP\plugins\generic\dataverse\classes\components\listPanel;

use APP\core\Application;
use PKP\components\listPanels\ListPanel;
use APP\plugins\generic\dataverse\classes\components\forms\DraftDatasetFileForm;

class DatasetFilesListPanel extends ListPanel
{
    public $addFileLabel = '';
    public $additionalInstructions = '';
    public $dataversePluginApiUrl = '';
    public $fileListUrl = '';
    public $fileActionUrl = '';
    public $isLoading = false;
    public $canChangeFiles = true;
    public $addFileModalTitle = '';
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
                'additionalInstructions' => $this->additionalInstructions,
                'dataversePluginApiUrl' => $this->dataversePluginApiUrl,
                'fileListUrl' => $this->fileListUrl,
                'fileActionUrl' => $this->fileActionUrl,
                'addFileModalTitle' => $this->addFileModalTitle,
                'title' => $this->title,
                'canChangeFiles' => $this->canChangeFiles,
                'form' => $form->getConfig(),
                'deleteFileTitle' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
                'deleteFileMessage' => __('plugins.generic.dataverse.modal.confirmDelete'),
                'deleteFileConfirmLabel' => __('grid.action.deleteFile')
            ]
        );

        return $config;
    }

    private function getForm(): DraftDatasetFileForm
    {
        $request = Application::get()->getRequest();
        $addFileUrl = $this->fileActionUrl;

        if (str_contains($this->fileActionUrl, '/draftDatasetFiles')) {
            $submissionId = $this->submission->getId();
            $userId = $request->getUser()->getId();
            $addFileUrl .= "?submissionId=$submissionId&userId=$userId";
        }

        return new DraftDatasetFileForm(
            $addFileUrl,
            $request->getContext()
        );
    }
}
