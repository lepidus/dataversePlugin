<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DepositMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dataverse_deposits')) {
            Schema::create('dataverse_deposits', function (Blueprint $table) {
                $table->bigInteger('submission_id')->primary();
                $table->string('state', 20);
                $table->bigInteger('revision')->default(0);
                $table->string('persistent_id', 255)->nullable();
                $table->longText('manifest');
                $table->longText('receipts');
                $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            });
        }
    }
}
