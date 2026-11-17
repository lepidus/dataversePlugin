<?php

use APP\plugins\generic\dataverse\classes\deposit\DepositRepository;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\tests\DatabaseTestCase;

class DepositRepositoryTest extends DatabaseTestCase
{
    private int $submissionId;

    /**
     * @dataProvider remoteDatasetFailures
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testMissingRemoteDatasetRetiresTheCompletedDeposit(
        int $remoteStatus,
        bool $pending,
        int $expectedStatus,
        string $expectedState
    ): void {
        ini_set('error_log', '/dev/null');
        $repository = new DepositRepository();
        $repository->reserve($this->submissionId, []);
        $repository->transition($this->submissionId, 'reserved', 'ready', ['persistent_id' => 'doi:10.1234/ABC']);
        $study = Repo::dataverseStudy()->newDataObject();
        $study->setSubmissionId($this->submissionId);
        $study->setPersistentId('doi:10.1234/ABC');
        foreach (['EditUri', 'EditMediaUri', 'StatementUri', 'PersistentUri'] as $property) {
            $study->{'set' . $property}('');
        }
        $repository->complete($this->submissionId, function () use ($study): void {
            $study->setId(Repo::dataverseStudy()->add($study));
        });
        $actions = $this->getMockBuilder(\APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions::class)
            ->disableOriginalConstructor()->onlyMethods(['get'])->getMock();
        $actions->method('get')->willThrowException(
            new \APP\plugins\generic\dataverse\classes\exception\DataverseException('Gone', $remoteStatus)
        );
        $client = Mockery::mock('overload:APP\\plugins\\generic\\dataverse\\dataverseAPI\\DataverseClient');
        $client->shouldReceive('getDatasetActions')->andReturn($actions);
        $handler = $this->getMockBuilder(\APP\plugins\generic\dataverse\api\v1\datasets\DatasetHandler::class)
            ->disableOriginalConstructor()->onlyMethods(['getAuthorizedContextObject'])->getMock();
        $handler->method('getAuthorizedContextObject')->willReturn(Repo::submission()->get($this->submissionId));

        if ($pending) {
            DB::table('dataverse_deposits')->where('submission_id', $this->submissionId)
                ->update(['state' => 'uploading']);
        }
        $response = $handler->get(null, new \PKP\core\APIResponse(), ['studyId' => $study->getId()]);

        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertSame(
            $expectedState === 'retired',
            Repo::dataverseStudy()->getBySubmissionId($this->submissionId) === null
        );
        $this->assertSame($expectedState, DB::table('dataverse_deposits')
            ->where('submission_id', $this->submissionId)->value('state'));
        if ($expectedState === 'retired') {
            $this->assertSame('reserved', (new DepositRepository())->reserve($this->submissionId, [])['state']);
        }
    }

    public static function remoteDatasetFailures(): array
    {
        return [
            'missing dataset' => [404, false, 404, 'retired'],
            'unavailable server' => [503, false, 503, 'complete'],
            'unconfirmed deposit' => [404, true, 409, 'uploading'],
        ];
    }

    public function setUp(): void
    {
        parent::setUp();
        $submission = new Submission();
        $submission->setData('contextId', 1);
        $this->submissionId = Repo::submission()->add($submission, new Publication(), DAORegistry::getDAO('JournalDAO')->getById(1));
    }

    protected function getAffectedTables(): array
    {
        return array_merge(parent::getAffectedTables(), ['dataverse_deposits', 'dataverse_studies']);
    }

    public function tearDown(): void
    {
        Repo::submission()->delete(Repo::submission()->get($this->submissionId));
        parent::tearDown();
    }

    public function testStaleWorkerCannotRepeatAnUploadAfterAnotherWorkerReturnsToReady(): void
    {
        $first = new DepositRepository();
        $first->reserve($this->submissionId, []);
        $first->transition($this->submissionId, 'reserved', 'creating');
        $first->transition($this->submissionId, 'creating', 'ready', ['persistent_id' => 'doi:10.1234/ABC']);
        $second = new DepositRepository();
        $second->reserve($this->submissionId, []);
        $first->transition($this->submissionId, 'ready', 'uploading');
        $first->transition($this->submissionId, 'uploading', 'ready', ['receipts' => '{"0":[{"id":22}]}']);
        $this->expectException(RuntimeException::class);
        $second->transition($this->submissionId, 'ready', 'uploading');
    }

    public function testOnlyOneRequestCanBeginCreation(): void
    {
        $first = new DepositRepository();
        $second = new DepositRepository();
        $first->reserve($this->submissionId, []);
        $second->reserve($this->submissionId, []);
        $this->assertSame(1, DB::table('dataverse_deposits')->where('submission_id', $this->submissionId)->count());
        $first->transition($this->submissionId, 'reserved', 'creating');
        $this->expectException(RuntimeException::class);
        $second->transition($this->submissionId, 'reserved', 'creating');
    }

    public function testRetirementPreservesRevisionAndRejectsWorkersFromPreviousIntent(): void
    {
        $first = new DepositRepository();
        $stale = new DepositRepository();
        $first->reserve($this->submissionId, []);
        $stale->reserve($this->submissionId, []);
        $first->transition($this->submissionId, 'reserved', 'ready');
        $first->complete($this->submissionId, fn () => null);
        $first->retire($this->submissionId, fn () => null);
        $next = $first->reserve($this->submissionId, [['sourceId' => 9]]);
        $this->assertSame('reserved', $next['state']);
        $this->assertGreaterThan(0, $next['revision']);
        $this->expectException(RuntimeException::class);
        $stale->transition($this->submissionId, 'reserved', 'creating');
    }

    public function testManualAssociationAfterRetirementPreventsNewDeposit(): void
    {
        $repository = new DepositRepository();
        $repository->reserve($this->submissionId, []);
        $repository->transition($this->submissionId, 'reserved', 'ready');
        $repository->complete($this->submissionId, fn () => null);
        $repository->retire($this->submissionId, fn () => null);
        DB::table('dataverse_studies')->insert([
            'submission_id' => $this->submissionId,
            'edit_uri' => '', 'edit_media_uri' => '', 'statement_uri' => '', 'persistent_uri' => '',
            'persistent_id' => 'doi:10.1234/MANUAL',
        ]);
        $this->expectException(RuntimeException::class);
        $repository->reserve($this->submissionId, []);
    }

    public function testUnknownMutationCannotBeRetired(): void
    {
        $repository = new DepositRepository();
        $repository->reserve($this->submissionId, []);
        $repository->transition($this->submissionId, 'reserved', 'creating');
        $this->expectException(RuntimeException::class);
        $repository->retire($this->submissionId, fn () => $this->fail('Removed uncertain association'));
    }

    public function testLocalFinalizationRollsBackToReady(): void
    {
        $repository = new DepositRepository();
        $repository->reserve($this->submissionId, []);
        $repository->transition($this->submissionId, 'reserved', 'ready');
        try {
            $repository->complete($this->submissionId, function (): void {
                throw new RuntimeException('Local publication update failed');
            });
            $this->fail('Expected transaction failure');
        } catch (RuntimeException $e) {
            $this->assertSame('ready', DB::table('dataverse_deposits')->where('submission_id', $this->submissionId)->value('state'));
        }
    }
}
