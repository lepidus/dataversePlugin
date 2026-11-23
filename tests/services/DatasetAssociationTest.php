<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\migrations\UniquePersistentIdMigration;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class DatasetAssociationTest extends PKPTestCase
{
    private const PERSISTENT_ID = 'doi:10.5072/FK2/RACE01';

    private $context;
    private int $submissionId;
    private int $otherSubmissionId;

    protected function setUp(): void
    {
        parent::setUp();
        (new UniquePersistentIdMigration())->up();
        DAORegistry::registerDAO('DataverseConfigurationDAO', new DataverseConfigurationDAO());

        $contextDAO = Application::getContextDAO();
        $this->context = $contextDAO->newDataObject();
        $this->context->setPath('dataverse-test-' . uniqid());
        $this->context->setPrimaryLocale('en');
        $this->context->setId($contextDAO->insertObject($this->context));
        DAORegistry::getDAO('PluginSettingsDAO')->updateSetting(
            $this->context->getId(),
            'dataverseplugin',
            'dataverseUrl',
            'https://demo.dataverse.org/dataverse/test'
        );
        $this->submissionId = $this->createSubmission();
        $this->otherSubmissionId = $this->createSubmission();
    }

    protected function tearDown(): void
    {
        Application::getContextDAO()->deleteObject($this->context);
        parent::tearDown();
    }

    private function createSubmission(): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());

        return Repo::submission()->add($submission, new Publication(), $this->context);
    }

    private function insertStudy(int $submissionId, string $persistentId): void
    {
        DB::table('dataverse_studies')->insert([
            'submission_id' => $submissionId,
            'edit_uri' => '',
            'edit_media_uri' => '',
            'statement_uri' => '',
            'persistent_uri' => '',
            'persistent_id' => $persistentId,
        ]);
    }

    private function mockCollectionCheck(?callable $duringCheck = null, string $canonicalPersistentId = self::PERSISTENT_ID): void
    {
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('findInCurrentCollection')->andReturnUsing(function () use ($duringCheck, $canonicalPersistentId) {
            if ($duringCheck) {
                $duringCheck();
            }
            return $canonicalPersistentId;
        });

        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
    }

    private function insertDeposit(int $submissionId, string $state, string $persistentId): void
    {
        DB::table('dataverse_deposits')->insert([
            'submission_id' => $submissionId,
            'state' => $state,
            'persistent_id' => $persistentId,
            'manifest' => '[]',
            'receipts' => '[]',
        ]);
    }

    private function countStudiesWithPersistentId(): int
    {
        return DB::table('dataverse_studies')
            ->whereIn('submission_id', [$this->submissionId, $this->otherSubmissionId])
            ->where('persistent_id', self::PERSISTENT_ID)
            ->count();
    }

    public function testDatasetAssociatedConcurrentlyWithAnotherSubmissionIsNotAssociatedTwice(): void
    {
        $this->mockCollectionCheck(fn () => $this->insertStudy($this->otherSubmissionId, self::PERSISTENT_ID));

        $result = (new DatasetService())->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertSame(1, $this->countStudiesWithPersistentId());
        $this->assertNull(Repo::dataverseStudy()->getBySubmissionId($this->submissionId));
    }

    public function testPersistentIdInAnotherLetterCaseIsAlreadyAssociated(): void
    {
        $this->mockCollectionCheck();
        $this->insertStudy($this->otherSubmissionId, self::PERSISTENT_ID);

        $result = (new DatasetService())->associate($this->submissionId, strtolower(self::PERSISTENT_ID));

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertSame(1, $this->countStudiesWithPersistentId());
    }

    public function testAssociatedPersistentIdIsStoredInCanonicalForm(): void
    {
        $this->mockCollectionCheck();

        $result = (new DatasetService())->associate($this->submissionId, 'DOI:10.5072/fk2/race01');

        $this->assertSame('success', $result['status']);
        $study = Repo::dataverseStudy()->getBySubmissionId($this->submissionId);
        $this->assertSame(self::PERSISTENT_ID, $study->getPersistentId());
        $this->assertSame('https://doi.org/10.5072/FK2/RACE01', $study->getPersistentUri());
    }

    public function testPersistentIdIsStoredAsDataverseReturnsIt(): void
    {
        $lowerCasePersistentId = 'doi:10.5072/fk2/race01';
        $this->mockCollectionCheck(null, $lowerCasePersistentId);

        $result = (new DatasetService())->associate($this->submissionId, $lowerCasePersistentId);

        $this->assertSame('success', $result['status']);
        $this->assertSame($lowerCasePersistentId, Repo::dataverseStudy()->getBySubmissionId($this->submissionId)->getPersistentId());
    }

    public function testDatasetOfAnUnfinishedDepositOfAnotherSubmissionIsAlreadyAssociated(): void
    {
        $this->mockCollectionCheck();
        $this->insertDeposit($this->otherSubmissionId, 'ready', self::PERSISTENT_ID);

        $result = (new DatasetService())->associate($this->submissionId, strtolower(self::PERSISTENT_ID));

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertNull(Repo::dataverseStudy()->getBySubmissionId($this->submissionId));
    }

    public function testDatasetDepositedConcurrentlyForAnotherSubmissionIsNotAssociated(): void
    {
        $this->mockCollectionCheck(fn () => $this->insertDeposit($this->otherSubmissionId, 'uploading', self::PERSISTENT_ID));

        $result = (new DatasetService())->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertNull(Repo::dataverseStudy()->getBySubmissionId($this->submissionId));
    }

    public function testDatasetOfARetiredDepositOfAnotherSubmissionCanBeAssociated(): void
    {
        $this->mockCollectionCheck();
        $this->insertDeposit($this->otherSubmissionId, 'retired', self::PERSISTENT_ID);

        $result = (new DatasetService())->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('success', $result['status']);
    }
}
