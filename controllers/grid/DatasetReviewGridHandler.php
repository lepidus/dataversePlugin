<?php

namespace APP\plugins\generic\dataverse\controllers\grid;

use PKP\controllers\grid\GridHandler;
use APP\core\Application;
use PKP\security\Role;
use APP\submission\Submission;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;
use PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\controllers\grid\DatasetReviewGridColumn;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;

class DatasetReviewGridHandler extends GridHandler
{
    private $study;

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER],
            ['fetchGrid', 'fetchRow']
        );
    }

    public function getSubmission(): Submission
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $submissionId = $this->getSubmission()->getId();
        $this->study = Repo::dataverseStudy()->getBySubmissionId($submissionId);

        $this->setTitle('plugins.generic.dataverse.researchData');
        $this->addColumn(new DatasetReviewGridColumn($this->study));
        $this->setEmptyRowText('plugins.generic.dataverse.noResearchData');
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $context = $request->getContext();
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', !$context->getData('restrictReviewerFileAccess')));

        $stageId = $request->getUserVar('stageId');
        $this->addPolicy(new WorkflowStageRequiredPolicy($stageId));
        $this->addPolicy(new ReviewRoundRequiredPolicy($request, $args));
        $this->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId'));

        return parent::authorize($request, $args, $roleAssignments);
    }

    protected function loadData($request, $filter)
    {
        $rowsData = [];

        if (!is_null($this->study)) {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($this->study->getPersistentId());
            $submission = $this->getSubmission();
            $selectedDataFilesForReview = $submission->getData('selectedDataFilesForReview');

            foreach ($dataset->getFiles() as $datasetFile) {
                if (in_array($datasetFile->getId(), $selectedDataFilesForReview)) {
                    $rowsData[$datasetFile->getId()] = $datasetFile;
                }
            }
        }

        return $rowsData;
    }
}
