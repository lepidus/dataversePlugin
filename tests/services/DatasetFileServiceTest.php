<?php

use APP\plugins\generic\dataverse\classes\facades\Repo as DataverseRepo;
use APP\plugins\generic\dataverse\classes\services\DatasetFileService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use APP\submission\Submission;
use PKP\tests\DatabaseTestCase;

class DatasetFileServiceTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithRealDataverse();
        $this->registerPlugin('workflow');
        $this->submission = $this->createDepositedSubmission();
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

    private function getStudy()
    {
        return DataverseRepo::dataverseStudy()->getBySubmissionId($this->submission->getId());
    }

    private function getDatasetFiles(): array
    {
        $datasetFiles = (new DataverseClient())->getDatasetFileActions()->getByDatasetId($this->getStudy()->getPersistentId());

        return array_combine(
            array_map(fn ($datasetFile) => $datasetFile->getFileName(), $datasetFiles),
            array_map(fn ($datasetFile) => $datasetFile->getId(), $datasetFiles)
        );
    }

    public function testAddUploadsTheFileToTheDataset(): void
    {
        $temporaryFileId = $this->createTemporaryFile('example.json', 'application/json', '{"example": true}');

        (new DatasetFileService())->add($this->getStudy(), $temporaryFileId);

        $this->assertEqualsCanonicalizing(
            ['LEIAME.pdf', 'Planilha_de_dados.json', 'example.json'],
            array_keys($this->getDatasetFiles())
        );
        $this->assertEventLogged(
            $this->submission,
            __('plugins.generic.dataverse.log.researchDataFileAdded', ['filename' => 'example.json'])
        );
    }

    public function testDeleteRemovesTheFileFromTheDataset(): void
    {
        $fileId = $this->getDatasetFiles()['Planilha_de_dados.json'];

        (new DatasetFileService())->delete($this->getStudy(), (string) $fileId, 'Planilha_de_dados.json');

        $this->assertEquals(['LEIAME.pdf'], array_keys($this->getDatasetFiles()));
        $this->assertEventLogged(
            $this->submission,
            __('plugins.generic.dataverse.log.researchDataFileDeleted', ['filename' => 'Planilha_de_dados.json'])
        );
    }
}
