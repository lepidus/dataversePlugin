<?php

namespace APP\plugins\generic\dataverse\classes\components\listPanel;

use APP\core\Application;
use PKP\components\listPanels\ListPanel;
use APP\plugins\generic\dataverse\classes\components\forms\DraftDatasetFileForm;

class DatasetFilesListPanel extends ListPanel
{
    public $addFileLabel = '';
    public $additionalInstructions = '';
    public $fileListUrl = '';
    public $fileActionUrl = '';
    public $isLoading = false;
    public $canChangeFiles = true;
    public $addFileModalTitle = '';
    public $title = '';

    public function getConfig()
    {
        $config = parent::getConfig();
        $form = $this->getForm();

        $config = array_merge(
            $config,
            [
                'addFileLabel' => $this->addFileLabel,
                'additionalInstructions' => $this->additionalInstructions,
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

        return new DraftDatasetFileForm(
            $this->fileActionUrl,
            $request->getContext()
        );
    }
}
