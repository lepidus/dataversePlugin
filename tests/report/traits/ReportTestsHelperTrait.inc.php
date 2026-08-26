<?php

import('classes.submission.Submission');
import('classes.publication.Publication');
import('classes.log.SubmissionEventLogEntry');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');

trait ReportTestsHelperTrait
{
    private function createTestContext()
    {
        $contextDAO = Application::getContextDAO();
        $context = $contextDAO->newDataObject();
        $context->setPath('test');
        $context->setPrimaryLocale('en_US');
        $id = $contextDAO->insertObject($context);
        $context->setId($id);

        return $context;
    }

    private function addDecision(int $decisionType, int $submissionId, ?string $dateDecided = null)
    {
        DAORegistry::getDAO('EditDecisionDAO')->updateEditorDecision(
            $submissionId,
            [
                'editDecisionId' => null,
                'editorId' => 1,
                'decision' => $decisionType,
                'dateDecided' => $dateDecided ?? Core::getCurrentDate()
            ]
        );
    }

    private function addStudy(int $submissionId)
    {
        $studyDAO = new DataverseStudyDAO();
        $study = $studyDAO->newDataObject();
        $study->setAllData([
            'submissionId' => $submissionId,
            'persistentId' => 'testId',
            'persistentUri' => 'testUri',
            'editUri' => 'testEditUri',
            'editMediaUri' => 'testEditMediaUri',
            'statementUri' => 'testStatementUri',
        ]);
        $studyDAO->insertStudy($study);
    }

    private function addEventLogToSubmission(int $submissionId, string $message, bool $isTranslated = false)
    {
        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
        $eventLog = $submissionEventLogDao->newDataObject();
        $eventLog->setDateLogged(Core::getCurrentDate());
        $eventLog->setUserId(rand());
        $eventLog->setSubmissionId($submissionId);
        $eventLog->setEventType(SUBMISSION_LOG_SUBMISSION_SUBMIT);
        $eventLog->setMessage($message);
        $eventLog->setParams([]);
        $eventLog->setIsTranslated((int) $isTranslated);
        $submissionEventLogDao->insertObject($eventLog);
    }

    private function addDataStatementTypesToSubmission(Submission $submission, array $dataStatementTypes)
    {
        $publication = $submission->getCurrentPublication();

        $publication->setData('dataStatementTypes', $dataStatementTypes);
        DAORegistry::getDAO('PublicationDAO')->updateObject($publication);
    }

    private function createTestSubmission(
        ?int $status = null,
        ?int $decision = null,
        bool $withDataset = false,
        ?string $dateSubmitted = null,
    ): Submission {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->newDataObject();
        $submission->setAllData([
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
            'contextId' => $this->context->getId(),
            'status' => $status,
            'dateSubmitted' => $dateSubmitted ?? Core::getCurrentDate()
        ]);
        $submissionDao->insertObject($submission);

        $publication = DAORegistry::getDAO('PublicationDAO')->newDataObject();
        $publication->setData('submissionId', $submission->getId());
        $publicationId = DAORegistry::getDAO('PublicationDAO')->insertObject($publication);

        if ($decision) {
            $this->addDecision($decision, $submission->getId());
        }

        if ($withDataset) {
            $this->addStudy($submission->getId());
        }

        $submission->setData('currentPublicationId', $publicationId);
        $submissionDao->updateObject($submission);

        return $submissionDao->getById($submission->getId());
    }
}
