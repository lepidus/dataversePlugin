<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.deposit.DepositRepository');
import('plugins.generic.dataverse.classes.services.DatasetService');
import('plugins.generic.dataverse.classes.dispatchers.DataStatementDispatcher');
import('plugins.generic.dataverse.DataversePlugin');

class DepositRepositoryTest extends DatabaseTestCase
{
    private int $submissionId;
    private int $publicationId;

    protected function getMockedRegistryKeys(): array
    {
        return ['request', 'sessionManager'];
    }

    public function setUp(): void
    {
        parent::setUp();
        new DataStatementDispatcher(new DataversePlugin());
        Services::get('schema')->get('publication', true);
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->newDataObject();
        $submission->setData('contextId', 1);
        $this->submissionId = $submissionDao->insertObject($submission);

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->newDataObject();
        $publication->setData('submissionId', $this->submissionId);
        $publicationId = $publicationDao->insertObject($publication);
        $this->publicationId = $publicationId;
        $submission->setData('currentPublicationId', $publicationId);
        $submissionDao->updateObject($submission);
    }

    protected function getAffectedTables(): array
    {
        return ['dataverse_deposits', 'dataverse_studies'];
    }

    public function tearDown(): void
    {
        $logIds = Capsule::table('event_log')->where('assoc_type', ASSOC_TYPE_SUBMISSION)
            ->where('assoc_id', $this->submissionId)->pluck('log_id')->all();
        if ($logIds) {
            Capsule::table('event_log_settings')->whereIn('log_id', $logIds)->delete();
            Capsule::table('event_log')->whereIn('log_id', $logIds)->delete();
        }
        Capsule::table('dataverse_deposits')->where('submission_id', $this->submissionId)->delete();
        Capsule::table('dataverse_studies')->where('submission_id', $this->submissionId)->delete();
        Capsule::table('publication_settings')->where('publication_id', $this->publicationId)->delete();
        Capsule::table('publications')->where('publication_id', $this->publicationId)->delete();
        Capsule::table('submission_settings')->where('submission_id', $this->submissionId)->delete();
        Capsule::table('submissions')->where('submission_id', $this->submissionId)->delete();
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
        $this->assertSame(1, Capsule::table('dataverse_deposits')->where('submission_id', $this->submissionId)->count());
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
        Capsule::table('dataverse_studies')->insert([
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
            $this->assertSame('ready', Capsule::table('dataverse_deposits')->where('submission_id', $this->submissionId)->value('state'));
        }
    }

    public function testFinalizationUpdatesPublicationAndWritesEventWithCoreServices(): void
    {
        if (defined('PHPUNIT_CURRENT_MOCK_ENV') && PHPUNIT_CURRENT_MOCK_ENV === 'env1') {
            $this->markTestSkipped('OJS 3.3 env1 MockValidation lacks isLoggedInAs; run this integration test with env2.');
        }
        import('lib.pkp.classes.session.SessionManager');
        $sessionManager = $this->getMockBuilder(SessionManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUserSession'])
            ->getMock();
        $sessionManager->method('getUserSession')->willReturn(DAORegistry::getDAO('SessionDAO')->newDataObject());
        Registry::set('sessionManager', $sessionManager);
        import('classes.core.Request');
        $actor = DAORegistry::getDAO('UserDAO')->getById(1);
        $this->assertNotNull($actor);
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUser'])
            ->getMock();
        $request->method('getUser')->willReturn($actor);
        Registry::set('request', $request);
        $repository = new DepositRepository();
        $repository->reserve($this->submissionId, []);
        $repository->transition($this->submissionId, 'reserved', 'ready');

        $submission = Services::get('submission')->get($this->submissionId);
        $this->assertNotNull($submission->getCurrentPublication());
        $service = new DatasetService();
        $method = new ReflectionMethod(DatasetService::class, 'completeDeposit');
        $method->setAccessible(true);

        $repository->complete($this->submissionId, function () use ($method, $service, $submission): void {
            $method->invoke($service, $submission, 'doi:10.1234/ABC');
        });

        $publication = Services::get('submission')->get($this->submissionId)->getCurrentPublication();
        $this->assertContains(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $publication->getData('dataStatementTypes'));
        $this->assertSame(1, Capsule::table('event_log')
            ->where('assoc_type', ASSOC_TYPE_SUBMISSION)
            ->where('assoc_id', $this->submissionId)
            ->where('message', 'plugins.generic.dataverse.log.researchDataDeposited')->count());
        $this->assertSame('complete', Capsule::table('dataverse_deposits')
            ->where('submission_id', $this->submissionId)->value('state'));
    }
}
