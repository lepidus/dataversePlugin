<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class DepositMigration extends Migration
{
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('dataverse_deposits')) {
            Capsule::schema()->create('dataverse_deposits', function (Blueprint $table) {
                $table->bigInteger('submission_id')->primary();
                $table->string('state', 20);
                $table->bigInteger('revision')->default(0);
                $table->string('persistent_id', 255)->nullable();
                $table->longText('manifest');
                $table->longText('receipts');
            });
        }
    }
}
