<?php

use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use PKP\tests\DatabaseTestCase;

class ResearchDataSubmissionTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    private const DATA_FILE = ['Planilha_de_dados_ÇÕÔÁÀÃ.json', 'application/json', '{"measurements": [1, 2, 3]}'];
    private const README_FILE = ['LEIAME.pdf', 'application/pdf', '%PDF-1.4 readme of the research data'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithRealDataverse();
        $this->registerPlugin();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private function createDataverseSubmission(array $submissionData = []): \APP\submission\Submission
    {
        return $this->createSubmission(
            ['dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]],
            $submissionData
        );
    }

    private function validate(\APP\submission\Submission $submission): array
    {
        return Repo::submission()->validateSubmit(Repo::submission()->get($submission->getId()), $this->context);
    }

    public function testResearchDataRulesOnlyApplyWhenDepositingInDataverse(): void
    {
        $submission = $this->createSubmission([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT],
        ]);

        $errors = $this->validate($submission);

        $this->assertArrayNotHasKey('datasetFiles', $errors);
        $this->assertArrayNotHasKey('datasetSubject', $errors);
    }

    public function testResearchDataFilesAreRequired(): void
    {
        $errors = $this->validate($this->createDataverseSubmission());

        $this->assertEquals([__('plugins.generic.dataverse.error.researchData.required')], $errors['datasetFiles']);
    }

    public function testResearchDataMustNotRepeatTheGalleyFile(): void
    {
        $submission = $this->createDataverseSubmission();
        $this->addSubmissionFile($submission, 'dummy.pdf', self::README_FILE[2]);
        $this->addDraftDatasetFile($submission, 'Data_detailing.pdf', 'application/pdf', self::README_FILE[2]);
        $this->addDraftDatasetFile($submission, ...self::DATA_FILE);

        $errors = $this->validate($submission);

        $this->assertEquals([__('plugins.generic.dataverse.notification.galleyContainsResearchData')], $errors['datasetFiles']);
    }

    public function testResearchDataRequiresAReadmeFile(): void
    {
        $submission = $this->createDataverseSubmission();
        $this->addDraftDatasetFile($submission, ...self::DATA_FILE);

        $errors = $this->validate($submission);

        $this->assertEquals([__('plugins.generic.dataverse.error.readmeFile.required')], $errors['datasetFiles']);
    }

    public function testResearchDataCannotBeOnlyTheReadmeFile(): void
    {
        $submission = $this->createDataverseSubmission();
        $this->addDraftDatasetFile($submission, ...self::README_FILE);

        $errors = $this->validate($submission);

        $this->assertEquals([__('plugins.generic.dataverse.error.notSolelyReadmeFile')], $errors['datasetFiles']);
    }

    public function testReadmeWithDataFilesIsAccepted(): void
    {
        $submission = $this->createDataverseSubmission();
        $this->addSubmissionFile($submission, 'dummy.pdf', '%PDF-1.4 manuscript');
        $this->addDraftDatasetFile($submission, ...self::README_FILE);
        $this->addDraftDatasetFile($submission, ...self::DATA_FILE);

        $errors = $this->validate($submission);

        $this->assertArrayNotHasKey('datasetFiles', $errors);
    }

    public function testDatasetSubjectIsRequired(): void
    {
        $errors = $this->validate($this->createDataverseSubmission());

        $this->assertEquals([__('plugins.generic.dataverse.error.datasetSubject.required')], $errors['datasetSubject']);
    }

    public function testDatasetMetadataIsAccepted(): void
    {
        $submission = $this->createDataverseSubmission([
            'datasetSubject' => 'Earth and Environmental Sciences',
            'datasetLicense' => 'CC BY 4.0',
            'datasetLanguage' => 'French',
            'datasetRelationType' => 'IsSupplementedBy',
        ]);

        $errors = $this->validate($submission);

        $this->assertEmpty(array_filter(
            array_keys($errors),
            fn (string $key) => str_starts_with($key, 'dataset') && $key !== 'datasetFiles'
        ));
    }
}
