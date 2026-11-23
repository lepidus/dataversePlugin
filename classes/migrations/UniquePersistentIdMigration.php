<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UniquePersistentIdMigration extends Migration
{
    public const INDEX_NAME = 'dataverse_studies_persistent_id_unique';

    public function up(): void
    {
        if (!Schema::hasTable('dataverse_studies') || Schema::hasIndex('dataverse_studies', self::INDEX_NAME)) {
            return;
        }

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

    private function getDuplicatedPersistentIds(): array
    {
        return DB::table('dataverse_studies')
            ->whereNotNull('persistent_id')
            ->orderBy('study_id')
            ->pluck('persistent_id')
            ->groupBy(fn (string $persistentId): string => DataverseStudy::normalizePersistentId($persistentId))
            ->filter(fn (Collection $persistentIds): bool => $persistentIds->count() > 1)
            ->map(fn (Collection $persistentIds): string => $persistentIds->implode(' = '))
            ->values()
            ->all();
    }
}
