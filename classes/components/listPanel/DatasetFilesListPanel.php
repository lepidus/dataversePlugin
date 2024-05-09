<?php

namespace APP\plugins\generic\dataverse\classes\components\listPanel;

use APP\core\Application;
use PKP\components\listPanels\ListPanel;
use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldHTML;
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
        $deleteForm = $this->getDeleteForm();

        $config = array_merge(
            $config,
            [
                'addFileLabel' => $this->addFileLabel,
                'datasetFilesApiUrl' => $this->datasetFilesApiUrl,
                'modalTitle' => $this->modalTitle,
                'title' => $this->title,
                'form' => $form->getConfig(),
                'deleteForm' => $deleteForm->getConfig()
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

    private function getDeleteForm(): FormComponent
    {
        $deleteDatasetFileForm = new FormComponent('deleteDatasetFile', 'DELETE', $this->datasetFilesApiUrl);

        $deleteDatasetFileForm->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
            ],
        ])->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ])->addField(new FieldHTML('deleteMessage', [
            'description' => __('plugins.generic.dataverse.modal.confirmDelete'),
            'groupId' => 'default'
        ]));

        return $deleteDatasetFileForm;
    }
}
