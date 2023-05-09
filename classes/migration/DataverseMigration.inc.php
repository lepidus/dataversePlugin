<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class DataverseMigration extends Migration
{
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('dataverse_studies')) {
            Capsule::schema()->create('dataverse_studies', function (Blueprint $table) {
                $table->bigInteger('study_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->string('edit_uri', 255);
                $table->string('edit_media_uri', 255);
                $table->string('statement_uri', 255);
                $table->string('persistent_uri', 255);
                $table->string('persistent_id', 255)->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('draft_dataset_files')) {
            Capsule::schema()->create('draft_dataset_files', function (Blueprint $table) {
                $table->bigInteger('draft_dataset_file_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('user_id');
                $table->bigInteger('file_id');
                $table->string('file_name', 255);
                $table->unique(['file_id'], 'temporary_files_id');
            });
        }

        Capsule::schema()->create('data_statements', function (Blueprint $table) {
            $table->bigInteger('data_statement_id')->autoIncrement();
            $table->bigInteger('type');
        });

        Capsule::schema()->create('data_statement_settings', function (Blueprint $table) {
            $table->bigIncrements('data_statement_setting_id');
            $table->bigInteger('data_statement_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->longText('setting_value')->nullable();
            $table->string('setting_type', 6)->nullable();
            $table->index(['data_statement_id_id'], 'data_statement_id_settings_id');
            $table->unique(['data_statement_id', 'locale', 'setting_name'], 'data_statement_settings_pkey');
        });
    }
}
