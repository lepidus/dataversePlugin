<?php

import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');

class DatasetDataReviewGridDataProvider extends ReviewGridDataProvider
{
    public function __construct()
    {
        $stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
        $fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SUBMISSION_FILE_REVIEW_FILE;
        parent::__construct($fileStage);
    }

    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $context = $request->getContext();
        $policy = new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', !$context->getData('restrictReviewerFileAccess'));

        $stageId = $request->getUserVar('stageId');
        import('lib.pkp.classes.security.authorization.internal.WorkflowStageRequiredPolicy');
        $policy->addPolicy(new WorkflowStageRequiredPolicy($stageId));

        // Add policy to ensure there is a review round id.
        import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
        $policy->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

        // Add policy to ensure there is a review assignment for certain operations.
        import('lib.pkp.classes.security.authorization.internal.ReviewAssignmentRequiredPolicy');
        $policy->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId'));

        return $policy;
    }

    public function loadData($filter = array())
    {
        import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileDAO');
        $draftDatasetFileDAO = new DraftDatasetFileDAO();
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($this->getSubmission()->getId());

        error_log(print_r($draftDatasetFiles, true));

        $researchDataFiles = [];
        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $researchDataFiles[$draftDatasetFile->getId()] = $draftDatasetFile;
        }

        return $researchDataFiles;
    }

    public function getRequestArgs()
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        return array_merge(parent::getRequestArgs(), array(
            'reviewAssignmentId' => $reviewAssignment->getId()
        ));
    }
}
