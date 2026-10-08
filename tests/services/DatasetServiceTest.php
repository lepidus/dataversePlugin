<?php

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\facades\Repo as DataverseRepo;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\services\DataverseService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\mail\Mailable;
use PKP\security\Role;
use PKP\tests\DatabaseTestCase;
use PKP\userGroup\UserGroup;

class DatasetServiceTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    private Submission $submission;
    private string $persistentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithRealDataverse();
        $this->registerPlugin('workflow');
        $this->submission = $this->createDepositedSubmission();
        $this->persistentId = $this->getStudy()->getPersistentId();
        $this->trackDataset($this->persistentId);
    }

    protected function tearDown(): void
    {
        try {
            $this->deleteDatasetsFromDataverse();
        } finally {
            $this->tearDownFixture();
            parent::tearDown();
            $this->restoreCoreSchemas();
        }
    }

    private function getStudy(?Submission $submission = null)
    {
        return DataverseRepo::dataverseStudy()->getBySubmissionId(($submission ?? $this->submission)->getId());
    }

    private function getDataStatementTypes(): array
    {
        return Repo::publication()->get($this->submission->getData('currentPublicationId'))->getData('dataStatementTypes');
    }

    private function getDatasetFromDataverse()
    {
        return (new DataverseClient())->getDatasetActions()->get($this->persistentId);
    }

    public function testDepositMarksResearchDataAsSubmittedToDataverse(): void
    {
        $this->assertContains(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $this->getDataStatementTypes());
        $this->assertEquals('https://doi.org/' . str_replace('doi:', '', $this->persistentId), $this->getStudy()->getPersistentUri());
        $this->assertFalse($this->getDatasetFromDataverse()->isPublished());
        $this->assertEventLogged($this->submission, __('plugins.generic.dataverse.log.researchDataDeposited', ['persistentId' => $this->persistentId]));
    }

    public function testUpdateSendsTheEditedMetadataToDataverse(): void
    {
        (new DatasetService())->update([
            'persistentId' => $this->persistentId,
            'title' => 'Test metadata editing',
            'description' => 'new description',
            'keywords' => ['mass public transport', 'sustainable cities', 'climate change'],
            'language' => 'English',
            'subject' => 'Computer and Information Science',
            'license' => 'CC0 1.0',
            'relationType' => 'IsCitedBy',
        ]);

        $dataset = $this->getDatasetFromDataverse();
        $this->assertEquals('Test metadata editing', $dataset->getTitle());
        $this->assertEquals('new description', $dataset->getDescription());
        $this->assertEquals(['mass public transport', 'sustainable cities', 'climate change'], $dataset->getKeywords());
        $this->assertEquals('English', $dataset->getLanguage());
        $this->assertEquals('Computer and Information Science', $dataset->getSubject());
        $this->assertEquals('CC0 1.0', $dataset->getLicense());
        $this->assertEquals('IsCitedBy', $dataset->getRelatedPublication()->getRelationType());
        $this->assertEventLogged($this->submission, __('plugins.generic.dataverse.log.researchDataUpdated'));
    }

    public function testDeleteRemovesTheDatasetFromDataverseAndFromTheSubmission(): void
    {
        (new DatasetService())->delete($this->getStudy(), null);

        $this->assertNull($this->getStudy());
        $this->assertNotContains(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $this->getDataStatementTypes());
        $this->assertEventLogged($this->submission, __('plugins.generic.dataverse.log.researchDataDeleted'));

        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(404);
        $this->getDatasetFromDataverse();
    }

    public function testManagerDeletingTheDatasetNotifiesTheDatasetContact(): void
    {
        Mail::fake();
        $this->context->setData('contactEmail', 'journal@example.org');
        $this->context->setData('contactName', 'Journal Manager');
        Application::getContextDAO()->updateObject($this->context);
        $this->grantRole(Role::ROLE_ID_MANAGER);
        $this->mockRequest($this->context->getPath() . '/workflow', $this->user->getId());

        (new DatasetService())->delete($this->getStudy(), 'The research data has been removed');

        Mail::assertSent(Mailable::class, function (Mailable $mailable) {
            return $mailable->hasTo('eostrom@mailinator.com')
                && str_contains($mailable->render(), 'The research data has been removed');
        });
    }

    public function testDisassociateKeepsTheDatasetInDataverse(): void
    {
        (new DatasetService())->disassociate($this->getStudy());

        $this->assertNull($this->getStudy());
        $this->assertNotContains(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $this->getDataStatementTypes());
        $this->assertEquals($this->persistentId, $this->getDatasetFromDataverse()->getPersistentId());
        $this->assertEventLogged($this->submission, __('plugins.generic.dataverse.log.researchDataDisassociate'));
    }

    public function testAssociateLinksTheSubmissionToADatasetInDataverse(): void
    {
        (new DatasetService())->disassociate($this->getStudy());

        $result = (new DatasetService())->associate($this->submission->getId(), $this->persistentId);

        $this->assertEquals(DataverseService::STATUS_SUCCESS, $result['status']);
        $this->assertEquals($this->persistentId, $this->getStudy()->getPersistentId());
        $this->assertEquals('https://doi.org/' . str_replace('doi:', '', $this->persistentId), $this->getStudy()->getPersistentUri());
    }

    public function testAssociateRejectsADatasetAlreadyAssociatedWithAnotherSubmission(): void
    {
        $submission = $this->createSubmission();

        $result = (new DatasetService())->associate($submission->getId(), $this->persistentId);

        $this->assertEquals(DataverseService::STATUS_ERROR, $result['status']);
        $this->assertEquals('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertNull($this->getStudy($submission));
    }

    public function testAssociateRejectsADatasetMissingFromDataverse(): void
    {
        $submission = $this->createSubmission();

        $result = (new DatasetService())->associate($submission->getId(), 'doi:10.12345/FK2/BLABLA.TESTE');

        $this->assertEquals(DataverseService::STATUS_NOT_FOUND, $result['status']);
        $this->assertEquals('plugins.generic.dataverse.error.associate.notFound', $result['message']);
        $this->assertNull($this->getStudy($submission));
    }

    public function testAssociateRejectsAnInvalidPersistentId(): void
    {
        $submission = $this->createSubmission();

        $result = (new DatasetService())->associate($submission->getId(), 'not-a-doi');

        $this->assertEquals('plugins.generic.dataverse.error.invalidPersistentId', $result['message']);
    }

    public function testAssociateRejectsASubmissionThatAlreadyHasADataset(): void
    {
        $result = (new DatasetService())->associate($this->submission->getId(), 'doi:10.12345/FK2/BLABLA.TESTE');

        $this->assertEquals('plugins.generic.dataverse.error.submissionHasStudy', $result['message']);
    }

    private function grantRole(int $roleId): void
    {
        $userGroup = UserGroup::create([
            'contextId' => $this->context->getId(),
            'roleId' => $roleId,
            'isDefault' => false,
            'showTitle' => true,
            'permitSelfRegistration' => false,
            'permitMetadataEdit' => true,
            'masthead' => false,
        ]);
        Repo::userGroup()->assignUserToGroup($this->user->getId(), $userGroup->id);
    }
}
