<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UniquePersistentIdMigration extends Migration
{
    public const INDEX_NAME = 'dataverse_studies_persistent_id_unique';

    public function up(): void
    {
        if (!Schema::hasTable('dataverse_studies') || self::hasIndex()) {
            return;
        }

        $this->normalizeStoredPersistentIds();

        $duplicatedPersistentIds = $this->getDuplicatedPersistentIds();
        if (!empty($duplicatedPersistentIds)) {
            error_log(
                'Dataverse: these datasets are associated with more than one submission, so the unique index on '
                . 'dataverse_studies.persistent_id was not created: ' . implode(', ', $duplicatedPersistentIds)
            );
            return;
        }

        Schema::table('dataverse_studies', function (Blueprint $table) {
            $table->unique(['persistent_id'], self::INDEX_NAME);
        });
    }

    public static function hasIndex(): bool
    {
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('dataverse_studies');

        return array_key_exists(self::INDEX_NAME, $indexes);
    }

    private function normalizeStoredPersistentIds(): void
    {
        DB::table('dataverse_studies')
            ->whereNotNull('persistent_id')
            ->select(['study_id', 'persistent_id'])
            ->orderBy('study_id')
            ->get()
            ->each(function (object $study): void {
                $normalizedPersistentId = DataverseStudy::normalizePersistentId($study->persistent_id);
                if ($normalizedPersistentId !== $study->persistent_id) {
                    DB::table('dataverse_studies')
                        ->where('study_id', $study->study_id)
                        ->update(['persistent_id' => $normalizedPersistentId]);
                }
            });
    }

    private function getDuplicatedPersistentIds(): array
    {
        return DB::table('dataverse_studies')
            ->whereNotNull('persistent_id')
            ->groupBy('persistent_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('persistent_id')
            ->all();
    }
}
