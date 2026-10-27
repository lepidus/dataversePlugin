<?php

use APP\plugins\generic\dataverse\classes\deposit\DepositRepository;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\tests\DatabaseTestCase;

class DepositRepositoryTest extends DatabaseTestCase
{
    use CreatesTestContext;

    private Context $context;
    private int $submissionId;

    public function setUp(): void
    {
        parent::setUp();
        $this->context = $this->createTestContext();
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());
        $this->submissionId = Repo::submission()->add($submission, new Publication(), $this->context);
    }

    public function tearDown(): void
    {
        $this->deleteTestContext($this->context);
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
