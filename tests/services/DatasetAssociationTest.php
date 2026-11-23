<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\migrations\UniquePersistentIdMigration;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DatasetAssociationTest extends PKPTestCase
{
    use CreatesTestContext;

    private const PERSISTENT_ID = 'doi:10.5072/FK2/RACE01';
    private const PERSISTENT_ID_IN_UPPER_CASE = 'DOI:10.5072/FK2/RACE01';

    private Context $context;
    private int $submissionId;
    private int $otherSubmissionId;

    protected function setUp(): void
    {
        parent::setUp();
        (new UniquePersistentIdMigration())->up();
        DAORegistry::registerDAO('DataverseConfigurationDAO', new DataverseConfigurationDAO());

        $this->context = $this->createTestContext();
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
        $this->deleteTestContext($this->context);
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

    private function mockCollectionCheck(?callable $duringCheck = null): void
    {
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('findInCurrentCollection')->andReturnUsing(function () use ($duringCheck) {
            if ($duringCheck) {
                $duringCheck();
            }
            return self::PERSISTENT_ID;
        });

        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
    }

    private function countStudiesWithPersistentId(): int
    {
        return DB::table('dataverse_studies')
            ->whereIn('submission_id', [$this->submissionId, $this->otherSubmissionId])
            ->whereRaw('UPPER(persistent_id) = ?', [self::PERSISTENT_ID_IN_UPPER_CASE])
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

    public function testDatasetStoredInAnotherLetterCaseBeforeTheIndexIsAlreadyAssociated(): void
    {
        $this->mockCollectionCheck();
        $this->insertStudy($this->otherSubmissionId, strtolower(self::PERSISTENT_ID));

        $result = (new DatasetService())->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertSame(1, $this->countStudiesWithPersistentId());
    }

    public function testDatasetAssociatedConcurrentlyInAnotherLetterCaseIsNotAssociatedTwice(): void
    {
        $this->mockCollectionCheck(fn () => $this->insertStudy($this->otherSubmissionId, strtolower(self::PERSISTENT_ID)));

        $result = (new DatasetService())->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertNull(Repo::dataverseStudy()->getBySubmissionId($this->submissionId));
    }

    public function testDatasetOfAnUnfinishedDepositOfAnotherSubmissionIsNotAssociated(): void
    {
        $this->mockCollectionCheck();
        DB::table('dataverse_deposits')->insert([
            'submission_id' => $this->otherSubmissionId,
            'state' => 'ready',
            'revision' => 3,
            'persistent_id' => strtolower(self::PERSISTENT_ID),
            'manifest' => '[]',
            'receipts' => '{}',
        ]);

        $result = (new DatasetService())->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertNull(Repo::dataverseStudy()->getBySubmissionId($this->submissionId));
    }

    public function testDatasetOfARetiredDepositOfAnotherSubmissionIsAssociated(): void
    {
        $this->mockCollectionCheck();
        DB::table('dataverse_deposits')->insert([
            'submission_id' => $this->otherSubmissionId,
            'state' => 'retired',
            'revision' => 5,
            'persistent_id' => self::PERSISTENT_ID,
            'manifest' => '[]',
            'receipts' => '{}',
        ]);

        $result = (new DatasetService())->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('success', $result['status']);
    }

    public function testAssociatedPersistentIdIsStoredAsDataverseStoresIt(): void
    {
        $this->mockCollectionCheck();

        $result = (new DatasetService())->associate($this->submissionId, 'DOI:10.5072/fk2/race01');

        $this->assertSame('success', $result['status']);
        $study = Repo::dataverseStudy()->getBySubmissionId($this->submissionId);
        $this->assertSame(self::PERSISTENT_ID, $study->getPersistentId());
        $this->assertSame('https://doi.org/10.5072/FK2/RACE01', $study->getPersistentUri());
    }
}
