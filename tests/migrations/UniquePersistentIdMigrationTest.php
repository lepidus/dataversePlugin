<?php

use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\migrations\UniquePersistentIdMigration;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\context\Context;
use PKP\tests\PKPTestCase;

class UniquePersistentIdMigrationTest extends PKPTestCase
{
    use CreatesTestContext;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->hasIndex()) {
            $this->dropIndex();
        }
        $this->context = $this->createTestContext();
    }

    protected function tearDown(): void
    {
        $this->deleteTestContext($this->context);
        (new UniquePersistentIdMigration())->up();
        parent::tearDown();
    }

    private function hasIndex(): bool
    {
        return Schema::hasIndex('dataverse_studies', UniquePersistentIdMigration::INDEX_NAME);
    }

    private function dropIndex(): void
    {
        Schema::table('dataverse_studies', function (Blueprint $table) {
            $table->dropUnique(UniquePersistentIdMigration::INDEX_NAME);
        });
    }

    private function insertStudy(string $persistentId): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());
        $submissionId = Repo::submission()->add($submission, new Publication(), $this->context);

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

    public function testStoredDoisAreNormalizedAndCannotBeAssociatedTwice(): void
    {
        $studyId = $this->insertStudy('doi:10.5072/fk2/migr01');

        (new UniquePersistentIdMigration())->up();

        $this->assertSame('doi:10.5072/FK2/MIGR01', $this->storedPersistentId($studyId));
        $this->assertTrue($this->hasIndex());
        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertStudy('doi:10.5072/FK2/MIGR01');
    }

    public function testIndexIsNotCreatedWhileADatasetIsAssociatedWithTwoSubmissions(): void
    {
        ini_set('error_log', '/dev/null');
        $firstStudyId = $this->insertStudy('doi:10.5072/fk2/migr02');
        $secondStudyId = $this->insertStudy('doi:10.5072/FK2/MIGR02');

        (new UniquePersistentIdMigration())->up();

        $this->assertFalse($this->hasIndex());
        $this->assertSame('doi:10.5072/FK2/MIGR02', $this->storedPersistentId($firstStudyId));
        $this->assertSame('doi:10.5072/FK2/MIGR02', $this->storedPersistentId($secondStudyId));
    }
}
