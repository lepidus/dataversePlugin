<?php

namespace APP\plugins\generic\dataverse\classes\observers\listeners;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\dataverse\classes\exception\MissingDepositFileException;
use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\services\DatasetService;

class DatasetDepositOnSubmission
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            DatasetDepositOnSubmission::class
        );
    }

    public function handle(SubmissionSubmitted $event): void
    {
        $submission = $event->submission;
        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');

        if (empty($dataStatementTypes) or !in_array(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            return;
        }

        $datasetFactory = new SubmissionDatasetFactory($submission);
        try {
            $dataset = $datasetFactory->getDataset();
        } catch (MissingDepositFileException $e) {
            (new NotificationManager())->createTrivialNotification(
                Application::get()->getRequest()->getUser()->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('plugins.generic.dataverse.error.sourceFileMissing')]
            );
            error_log('Dataverse deposit source file missing: submission=' . $submission->getId());
            return;
        }

        if (empty($dataset->getFiles()) or empty($dataset->getSubject())) {
            return;
        }

        $datasetService = new DatasetService();
        $depositInfo = $datasetService->deposit($submission, $dataset);
        if ($depositInfo['status'] != 'Success') {
            error_log('Dataverse API error: ' . $depositInfo['messageParams']['error']);
        }
    }
}
