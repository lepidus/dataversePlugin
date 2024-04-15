<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DataverseMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dataverse_studies')) {
            Schema::create('dataverse_studies', function (Blueprint $table) {
                $table->bigInteger('study_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->string('edit_uri', 255);
                $table->string('edit_media_uri', 255);
                $table->string('statement_uri', 255);
                $table->string('persistent_uri', 255);
                $table->string('persistent_id', 255)->nullable();

                $table->foreign('submission_id')
                    ->references('submission_id')
                    ->on('submissions')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('draft_dataset_files')) {
            Schema::create('draft_dataset_files', function (Blueprint $table) {
                $table->bigInteger('draft_dataset_file_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('user_id');
                $table->bigInteger('file_id');
                $table->string('file_name', 255);

                $table->foreign('submission_id')
                    ->references('submission_id')
                    ->on('submissions')
                    ->onDelete('cascade');

                $table->unique(['file_id'], 'temporary_files_id');
            });
        }
    }
}
