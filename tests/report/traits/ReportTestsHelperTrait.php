<?php

namespace APP\plugins\generic\dataverse\tests\report\traits;

use APP\core\Application;
use PKP\core\Core;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;

trait ReportTestsHelperTrait
{

    private function createTestContext()
    {
        $contextDAO = Application::getContextDAO();
        $context = $contextDAO->newDataObject();
        $context->setPath('test');
        $context->setPrimaryLocale('en');
        $id = $contextDAO->insertObject($context);
        $context->setId($id);

        return $context;
    }

    private function addDecision(int $decisionType, int $submissionId)
    {
        $decision = Repo::decision()->newDataObject([
            'decision' => $decisionType,
            'submissionId' => $submissionId,
            'dateDecided' => date(Core::getCurrentDate()),
            'editorId' => 1,
        ]);
        Repo::decision()->dao->insert($decision);
    }

    private function addStudy(int $submissionId)
    {
        $study = Repo::dataverseStudy()->newDataObject();
        $study->setAllData([
            'submissionId' => $submissionId,
            'persistentId' => 'testId',
            'persistentUri' => 'testUri',
            'editUri' => 'testEditUri',
            'editMediaUri' => 'testEditMediaUri',
            'statementUri' => 'testStatementUri',
        ]);
        Repo::dataverseStudy()->add($study);
    }

    private function addEventLogToSubmission(int $submissionId, string $message, bool $isTranslated = false)
    {
        $eventLog = Repo::eventLog()->newDataObject();
        $eventLog->setAllData([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'message' => $message,
            'isTranslated' => $isTranslated,
            'dateLogged' => Core::getCurrentDate()
        ]);
        Repo::eventLog()->add($eventLog);
    }

    private function createTestSubmission(?int $status = null, ?int $decision = null, bool $withDataset = false): Submission
    {
        $submission = new Submission();
        $submission->setAllData([
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
            'contextId' => $this->context->getId(),
            'status' => $status
        ]);

        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $this->context);
        $submission->setId($submissionId);

        if ($decision) {
            $this->addDecision($decision, $submission->getId());
        }

        if ($withDataset) {
            $this->addStudy($submission->getId());
        }

        return $submission;
    }
}
