<?php

import('lib.pkp.classes.controllers.grid.GridRow');

class DraftDatasetFileGridRow extends GridRow
{
    private $submission;

    private $publication;

    public function __construct($submission, $publication)
    {
        $this->submission = $submission;
        $this->publication = $publication;
        parent::__construct();
    }

    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        $fileId = $this->getId();
        if (!empty($fileId)) {
            $router = $request->getRouter();
            $actionArgs = $this->getRequestArgs();
            $actionArgs['fileId'] = $fileId;

            import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
            $this->addAction(new LinkAction(
                'deleteFile',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __('common.confirmDelete'),
                    __('grid.action.delete'),
                    $router->url($request, null, null, 'deleteFile', null, $actionArgs),
                    'modal_delete'
                ),
                __('grid.action.delete'),
                'delete'
            ));
        }
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function getRequestArgs(): array
    {
        return array(
            'submissionId' => $this->getSubmission()->getId(),
            'publicationId' => $this->getPublication()->getId(),
        );
    }
}
