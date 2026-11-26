<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class UniquePersistentIdMigration extends Migration
{
    public const INDEX_NAME = 'dataverse_studies_persistent_id_unique';

    public function up(): void
    {
        if (!Capsule::schema()->hasTable('dataverse_studies') || self::hasIndex()) {
            return;
        }

        $duplicatedPersistentIds = self::getDuplicatedPersistentIds();
        if (!empty($duplicatedPersistentIds)) {
            error_log(
                'Dataverse: these datasets are associated with more than one submission, so the unique index on '
                . 'dataverse_studies.persistent_id was not created: ' . implode(', ', $duplicatedPersistentIds)
            );
            return;
        }

        Capsule::schema()->table('dataverse_studies', function (Blueprint $table) {
            $table->unique(['persistent_id'], self::INDEX_NAME);
        });
    }

    public static function hasIndex(): bool
    {
        $indexes = Capsule::connection()->getDoctrineSchemaManager()->listTableIndexes('dataverse_studies');

        return array_key_exists(self::INDEX_NAME, $indexes);
    }

    public static function getDuplicatedPersistentIds(): array
    {
        return Capsule::table('dataverse_studies')
            ->whereNotNull('persistent_id')
            ->selectRaw('UPPER(persistent_id) AS duplicated_persistent_id')
            ->groupBy(Capsule::raw('UPPER(persistent_id)'))
            ->havingRaw('COUNT(*) > 1')
            ->pluck('duplicated_persistent_id')
            ->all();
    }
}
