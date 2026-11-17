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

    private function createService(?callable $duringCollectionCheck = null): DatasetService
    {
        $datasetActions = $this->createMock(DatasetActions::class);
        $datasetActions->method('isInCurrentCollection')->willReturnCallback(
            function () use ($duringCollectionCheck) {
                if ($duringCollectionCheck) {
                    $duringCollectionCheck();
                }
                return true;
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

    public function testAssociatedPersistentIdIsStoredInCanonicalForm(): void
    {
        $result = $this->createService()->associate($this->submissionId, 'DOI:10.5072/fk2/race01');

        $this->assertSame('success', $result['status']);
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($this->submissionId);
        $this->assertSame(self::PERSISTENT_ID, $study->getPersistentId());
        $this->assertSame('https://doi.org/10.5072/FK2/RACE01', $study->getPersistentUri());
    }
}
