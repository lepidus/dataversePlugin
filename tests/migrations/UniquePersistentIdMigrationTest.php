<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\migrations\UniquePersistentIdMigration;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\tests\PKPTestCase;

class UniquePersistentIdMigrationTest extends PKPTestCase
{
    private $context;
    private array $submissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (UniquePersistentIdMigration::hasIndex()) {
            Schema::table('dataverse_studies', function (Blueprint $table) {
                $table->dropUnique(UniquePersistentIdMigration::INDEX_NAME);
            });
        }

        $contextDAO = Application::getContextDAO();
        $this->context = $contextDAO->newDataObject();
        $this->context->setPath('dataverse-test-' . uniqid());
        $this->context->setPrimaryLocale('en');
        $this->context->setId($contextDAO->insertObject($this->context));
    }

    protected function tearDown(): void
    {
        DB::table('dataverse_studies')->whereIn('submission_id', $this->submissionIds)->delete();
        foreach ($this->submissionIds as $submissionId) {
            Repo::submission()->delete(Repo::submission()->get($submissionId));
        }
        Application::getContextDAO()->deleteObject($this->context);
        (new UniquePersistentIdMigration())->up();
        parent::tearDown();
    }

    private function insertStudy(string $persistentId): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());
        $submissionId = Repo::submission()->add($submission, new Publication(), $this->context);
        $this->submissionIds[] = $submissionId;

        return DB::table('dataverse_studies')->insertGetId([
            'submission_id' => $submissionId,
            'edit_uri' => '',
            'edit_media_uri' => '',
            'statement_uri' => '',
            'persistent_uri' => '',
            'persistent_id' => $persistentId,
        ], 'study_id');
    }

    private function storedPersistentId(int $studyId): string
    {
        return DB::table('dataverse_studies')->where('study_id', $studyId)->value('persistent_id');
    }

    public function testStoredPersistentIdsAreKeptAndCannotBeAssociatedTwice(): void
    {
        $studyId = $this->insertStudy('doi:10.5072/fk2/migr01');

        (new UniquePersistentIdMigration())->up();

        $this->assertSame('doi:10.5072/fk2/migr01', $this->storedPersistentId($studyId));
        $this->assertTrue(UniquePersistentIdMigration::hasIndex());
        $this->expectException(QueryException::class);
        $this->insertStudy('doi:10.5072/fk2/migr01');
    }

    public function testIndexIsNotCreatedWhileADatasetIsAssociatedWithTwoSubmissions(): void
    {
        $previousErrorLog = ini_set('error_log', '/dev/null');
        $firstStudyId = $this->insertStudy('doi:10.5072/fk2/migr02');
        $secondStudyId = $this->insertStudy('doi:10.5072/FK2/MIGR02');

        try {
            (new UniquePersistentIdMigration())->up();
        } finally {
            ini_set('error_log', $previousErrorLog);
        }

        $this->assertFalse(UniquePersistentIdMigration::hasIndex());
        $this->assertSame('doi:10.5072/fk2/migr02', $this->storedPersistentId($firstStudyId));
        $this->assertSame('doi:10.5072/FK2/MIGR02', $this->storedPersistentId($secondStudyId));
    }
}
