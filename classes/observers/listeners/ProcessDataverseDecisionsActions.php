<?php

namespace APP\plugins\generic\dataverse\classes\observers\listeners;

use Illuminate\Events\Dispatcher;
use APP\decision\Decision;
use PKP\observers\events\DecisionAdded;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class ProcessDataverseDecisionsActions
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            DecisionAdded::class,
            ProcessDataverseDecisionsActions::class
        );
    }

    public function handle(DecisionAdded $event): void
    {
        $submission = $event->submission;
        $decision = $event->decision->getData('decision');
        $selectedDataFiles = [];

        foreach ($event->actions as $action) {
            if ($decision == Decision::EXTERNAL_REVIEW && $action['id'] == 'selectDataFiles') {
                $selectedDataFiles = $action['selectedDataFilesForReview'];
                $this->saveSelectedDataFilesForReview($submission, $selectedDataFiles);
                break;
            }
            if ($decision == Decision::ACCEPT && $action['id'] == 'researchDataPublishNotice') {
                if ($action['shouldPublishResearchData']) {
                    $this->publishResearchData($submission);
                }
                break;
            }
            if (
                ($decision == Decision::DECLINE || $decision == Decision::INITIAL_DECLINE)
                && $action['id'] == 'researchDataDeleteNotice'
            ) {
                if ($action['shouldDeleteResearchData']) {
                    $this->deleteResearchData($submission);
                }
                break;
            }
        }
    }

    private function saveSelectedDataFilesForReview($submission, $selectedDataFiles)
    {
        if (!empty($selectedDataFiles)) {
            Repo::submission()->edit($submission, [
                'selectedDataFilesForReview' => $selectedDataFiles
            ]);
        }
    }

    private function publishResearchData($submission)
    {
        $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());

        $datasetService = new DatasetService();
        $datasetService->publish($submission, $study);
    }

    private function deleteResearchData($submission)
    {
        $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());

        $datasetService = new DatasetService();
        $datasetService->delete($study, null);
    }
}
