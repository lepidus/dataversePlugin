<?php

namespace APP\plugins\generic\dataverse\tests\report\traits;

use APP\core\Application;
use PKP\core\Core;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\DataversePlugin;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;

trait ReportTestsHelperTrait
{
    use CreatesTestContext;

    private $context;

    protected function getMockedRegistryKeys(): array
    {
        return ['hooks'];
    }

    private function setUpReportFixture(): void
    {
        $plugin = new DataversePlugin();
        $plugin->pluginPath = 'plugins/generic/dataverse';
        $plugin->addLocaleData();
        new DataStatementDispatcher($plugin);
        app()->get('schema')->get('publication', true);
        $this->context = $this->createTestContext();
    }

    private function tearDownReportFixture(): void
    {
        $this->deleteTestContext($this->context);
        app()->get('schema')->get('publication', true);
    }

    private function addDecision(int $decisionType, int $submissionId, ?string $dateDecided = null)
    {
        $decision = Repo::decision()->newDataObject([
            'decision' => $decisionType,
            'submissionId' => $submissionId,
            'dateDecided' => $dateDecided ?? date(Core::getCurrentDate()),
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

    private function addDataStatementTypesToSubmission(Submission $submission, array $dataStatementTypes)
    {
        $publication = $submission->getCurrentPublication();

        $publication->setData('dataStatementTypes', $dataStatementTypes);
        Repo::publication()->dao->update($publication);
    }

    private function createTestSubmission(
        ?int $status = null,
        ?int $decision = null,
        bool $withDataset = false,
        ?string $dateSubmitted = null
    ): Submission {
        $submission = new Submission();
        $submission->setAllData([
            'submissionProgress' => '',
            'contextId' => $this->context->getId(),
            'status' => $status,
            'dateSubmitted' => $dateSubmitted ?? Core::getCurrentDate()
        ]);

        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $this->context);
        $submission = Repo::submission()->get($submissionId);

        if ($decision) {
            $this->addDecision($decision, $submission->getId());
        }

        if ($withDataset) {
            $this->addStudy($submission->getId());
        }

        return $submission;
    }
}
