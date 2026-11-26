<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.migration.UniquePersistentIdMigration');

class UniquePersistentIdMigrationTest extends PKPTestCase
{
    private $studyIds = [];
    private $errorLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorLog = ini_get('error_log');
        if (!empty(UniquePersistentIdMigration::getDuplicatedPersistentIds())) {
            $this->markTestSkipped('The installation already associates a dataset with more than one submission.');
        }

        if (UniquePersistentIdMigration::hasIndex()) {
            Capsule::schema()->table('dataverse_studies', function (Blueprint $table) {
                $table->dropUnique(UniquePersistentIdMigration::INDEX_NAME);
            });
        }
    }

    protected function tearDown(): void
    {
        Capsule::table('dataverse_studies')->whereIn('study_id', $this->studyIds)->delete();
        (new UniquePersistentIdMigration())->up();
        ini_set('error_log', $this->errorLog);
        parent::tearDown();
    }

    private function insertStudy(string $persistentId): int
    {
        $studyId = Capsule::table('dataverse_studies')->insertGetId([
            'submission_id' => 0,
            'edit_uri' => '',
            'edit_media_uri' => '',
            'statement_uri' => '',
            'persistent_uri' => '',
            'persistent_id' => $persistentId,
        ], 'study_id');
        $this->studyIds[] = $studyId;

        return $studyId;
    }

    private function storedPersistentId(int $studyId): string
    {
        return Capsule::table('dataverse_studies')->where('study_id', $studyId)->value('persistent_id');
    }

    public function testStoredDoisKeepTheirCaseAndCannotBeAssociatedTwice(): void
    {
        $studyId = $this->insertStudy('doi:10.5072/fk2.migr01');

        (new UniquePersistentIdMigration())->up();

        $this->assertSame('doi:10.5072/fk2.migr01', $this->storedPersistentId($studyId));
        $this->assertTrue(UniquePersistentIdMigration::hasIndex());
        $this->expectException(QueryException::class);
        $this->insertStudy('doi:10.5072/fk2.migr01');
    }

    public function testIndexIsNotCreatedWhileADatasetIsAssociatedWithTwoSubmissions(): void
    {
        ini_set('error_log', '/dev/null');
        $firstStudyId = $this->insertStudy('doi:10.5072/fk2/migr02');
        $secondStudyId = $this->insertStudy('doi:10.5072/FK2/MIGR02');

        (new UniquePersistentIdMigration())->up();

        $this->assertFalse(UniquePersistentIdMigration::hasIndex());
        $this->assertSame('doi:10.5072/fk2/migr02', $this->storedPersistentId($firstStudyId));
        $this->assertSame('doi:10.5072/FK2/MIGR02', $this->storedPersistentId($secondStudyId));
    }
}
