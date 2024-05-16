<?php

namespace APP\plugins\generic\dataverse\classes\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;
use APP\core\Application;
use APP\log\event\SubmissionEventLogEntry;
use PKP\core\Core;
use APP\plugins\generic\dataverse\classes\facades\Repo;
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
        $publication = $event->submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');

        if (empty($dataStatementTypes) or !in_array(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            return;
        }

        $datasetFactory = new SubmissionDatasetFactory($submission);
        $dataset = $datasetFactory->getDataset();

        if (empty($dataset->getFiles()) or empty($dataset->getSubject())) {
            return;
        }

        $datasetService = new DatasetService();
        try {
            $datasetService->deposit($submission, $dataset);
        } catch (DataverseException $e) {
            $message = __('plugins.generic.dataverse.error.depositFailed', ['error' => $e->getMessage()]);
            $depositErrorEntry = Repo::eventLog()->newDataObject([
                'assocType' => Application::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
                'message' => $message,
                'isTranslated' => true,
                'dateLogged' => Core::getCurrentDate(),
            ]);
            Repo::eventLog()->add($depositErrorEntry);
        }
    }
}
