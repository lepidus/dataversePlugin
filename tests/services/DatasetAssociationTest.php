<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.services.DatasetService');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfigurationDAO');
import('plugins.generic.dataverse.classes.migration.UniquePersistentIdMigration');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');

class DatasetAssociationTest extends PKPTestCase
{
    private const PERSISTENT_ID = 'doi:10.5072/FK2/RACE01';

    private $context;
    private $submissionId;
    private $otherSubmissionId;

    protected function setUp(): void
    {
        parent::setUp();
        (new UniquePersistentIdMigration())->up();
        DAORegistry::registerDAO('DataverseStudyDAO', new DataverseStudyDAO());
        DAORegistry::registerDAO('DataverseConfigurationDAO', new DataverseConfigurationDAO());

        $contextDao = Application::getContextDAO();
        $this->context = $contextDao->newDataObject();
        $this->context->setPath('dataverse-test-' . uniqid());
        $this->context->setPrimaryLocale('en_US');
        $this->context->setId($contextDao->insertObject($this->context));
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
        $submissionIds = [$this->submissionId, $this->otherSubmissionId];
        Capsule::table('dataverse_studies')->whereIn('submission_id', $submissionIds)->delete();
        Capsule::table('dataverse_deposits')->whereIn('submission_id', $submissionIds)->delete();
        foreach ($submissionIds as $submissionId) {
            DAORegistry::getDAO('SubmissionDAO')->deleteById($submissionId);
        }
        Capsule::table('plugin_settings')->where('context_id', $this->context->getId())->delete();
        Application::getContextDAO()->deleteById($this->context->getId());
        parent::tearDown();
    }

    private function createSubmission(): int
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->newDataObject();
        $submission->setData('contextId', $this->context->getId());

        return $submissionDao->insertObject($submission);
    }

    private function insertStudy(int $submissionId, string $persistentId): void
    {
        Capsule::table('dataverse_studies')->insert([
            'submission_id' => $submissionId,
            'edit_uri' => '',
            'edit_media_uri' => '',
            'statement_uri' => '',
            'persistent_uri' => '',
            'persistent_id' => $persistentId,
        ]);
    }

    private function insertDeposit(int $submissionId, string $state, string $persistentId): void
    {
        Capsule::table('dataverse_deposits')->insert([
            'submission_id' => $submissionId,
            'state' => $state,
            'revision' => 1,
            'persistent_id' => $persistentId,
            'manifest' => '[]',
            'receipts' => '[]',
        ]);
    }

    private function createService(?callable $duringCollectionCheck = null, string $persistentIdInDataverse = self::PERSISTENT_ID): DatasetService
    {
        $datasetActions = $this->createMock(DatasetActions::class);
        $datasetActions->method('findInCurrentCollection')->willReturnCallback(
            function () use ($duringCollectionCheck, $persistentIdInDataverse) {
                if ($duringCollectionCheck) {
                    $duringCollectionCheck();
                }
                return $persistentIdInDataverse;
            }
        );
        $client = $this->createMock(DataverseClient::class);
        $client->method('getDatasetActions')->willReturn($datasetActions);

        $service = $this->getMockBuilder(DatasetService::class)->onlyMethods(['getDataverseClient'])->getMock();
        $service->method('getDataverseClient')->willReturn($client);

        return $service;
    }

    private function countStudiesWithPersistentId(): int
    {
        return Capsule::table('dataverse_studies')
            ->whereIn('submission_id', [$this->submissionId, $this->otherSubmissionId])
            ->where('persistent_id', self::PERSISTENT_ID)
            ->count();
    }

    public function testDatasetAssociatedConcurrentlyWithAnotherSubmissionIsNotAssociatedTwice(): void
    {
        $otherSubmissionId = $this->otherSubmissionId;
        $service = $this->createService(function () use ($otherSubmissionId) {
            $this->insertStudy($otherSubmissionId, self::PERSISTENT_ID);
        });

        $result = $service->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertSame(1, $this->countStudiesWithPersistentId());
        $this->assertNull(DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($this->submissionId));
    }

    public function testPersistentIdInAnotherLetterCaseIsAlreadyAssociated(): void
    {
        $this->insertStudy($this->otherSubmissionId, self::PERSISTENT_ID);

        $result = $this->createService()->associate($this->submissionId, strtolower(self::PERSISTENT_ID));

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertSame(1, $this->countStudiesWithPersistentId());
    }

    public function testAssociatedPersistentIdIsStoredAsDataverseReturnsIt(): void
    {
        $result = $this->createService(null, 'doi:10.5072/fk2.Race01')->associate($this->submissionId, 'DOI:10.5072/FK2.RACE01');

        $this->assertSame('success', $result['status']);
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($this->submissionId);
        $this->assertSame('doi:10.5072/fk2.Race01', $study->getPersistentId());
        $this->assertSame('https://doi.org/10.5072/fk2.Race01', $study->getPersistentUri());
    }

    public function testStudyIsFoundByItsPersistentIdInAnyCase(): void
    {
        $this->insertStudy($this->otherSubmissionId, 'doi:10.5072/fk2.Race02');

        $study = DAORegistry::getDAO('DataverseStudyDAO')->getByPersistentId('DOI:10.5072/FK2.RACE02');

        $this->assertSame($this->otherSubmissionId, (int) $study->getSubmissionId());
    }

    public function testDatasetOfAnotherSubmissionsUnfinishedDepositIsNotAssociated(): void
    {
        $this->insertDeposit($this->otherSubmissionId, 'ready', strtolower(self::PERSISTENT_ID));

        $result = $this->createService()->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertNull(DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($this->submissionId));
    }

    public function testDatasetDepositedConcurrentlyByAnotherSubmissionIsNotAssociated(): void
    {
        $otherSubmissionId = $this->otherSubmissionId;
        $service = $this->createService(function () use ($otherSubmissionId) {
            $this->insertDeposit($otherSubmissionId, 'uploading', self::PERSISTENT_ID);
        });

        $result = $service->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('plugins.generic.dataverse.error.associate.alreadyAssociated', $result['message']);
        $this->assertNull(DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($this->submissionId));
    }

    public function testDatasetOfAnotherSubmissionsRetiredDepositCanBeAssociated(): void
    {
        $this->insertDeposit($this->otherSubmissionId, 'retired', self::PERSISTENT_ID);

        $result = $this->createService()->associate($this->submissionId, self::PERSISTENT_ID);

        $this->assertSame('success', $result['status']);
    }
}
