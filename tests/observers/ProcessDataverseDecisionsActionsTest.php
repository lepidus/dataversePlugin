<?php

use APP\decision\Decision;
use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\observers\listeners\ProcessDataverseDecisionsActions;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use APP\submission\Submission;
use PKP\decision\types\SendExternalReview;
use PKP\observers\events\DecisionAdded;
use PKP\tests\DatabaseTestCase;

class ProcessDataverseDecisionsActionsTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithPlaceholderDataverse();
        $this->registerPlugin('workflow');
        $this->reloadSchemas(['submission']);
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private function sendToExternalReview(Submission $submission, array $selectedDataFiles): Submission
    {
        $decision = Repo::decision()->newDataObject(['decision' => Decision::EXTERNAL_REVIEW]);
        $actions = [['id' => 'selectDataFiles', 'selectedDataFilesForReview' => $selectedDataFiles]];
        $event = new DecisionAdded($decision, new SendExternalReview(), $submission, $this->user, $this->context, $actions);

        (new ProcessDataverseDecisionsActions())->handle($event);

        return Repo::submission()->get($submission->getId());
    }

    public function testFilesSelectedForReviewAreSaved(): void
    {
        $submission = $this->sendToExternalReview($this->createSubmission(), [41, 42]);

        $this->assertEquals([41, 42], $submission->getData('selectedDataFilesForReview'));
    }

    public function testEmptySelectionRevokesTheFilesSelectedBefore(): void
    {
        $submission = $this->sendToExternalReview($this->createSubmission(), [41]);

        $submission = $this->sendToExternalReview($submission, []);

        $this->assertEmpty($submission->getData('selectedDataFilesForReview'));
    }
}
