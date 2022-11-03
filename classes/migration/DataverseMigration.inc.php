<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class DataverseMigration extends Migration {
    
    public function up(): void
	{
        if(!Capsule::schema()->hasTable('dataverse_studies')) {
            Capsule::schema()->create('dataverse_studies', function (Blueprint $table) {
                $table->bigInteger('study_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->string('edit_uri', 255);
                $table->string('edit_media_uri', 255);
                $table->string('statement_uri', 255);
                $table->string('persistent_uri', 255);
                $table->text('data_citation')->nullable();
                $table->string('dataset_url', 255)->nullable();
            });
        }

		if(!Capsule::schema()->hasTable('dataverse_files')) {
            Capsule::schema()->create('dataverse_files', function (Blueprint $table) {
				$table->bigInteger('file_id')->autoIncrement();
				$table->bigInteger('study_id');
				$table->bigInteger('submission_id');
				$table->bigInteger('submission_file_id');
				$table->string('content_uri', 255);
			});
		}

        if(!Capsule::schema()->hasTable('draft_dataset_files')) {
            Capsule::schema()->create('draft_dataset_files', function (Blueprint $table) {
                $table->bigInteger('draft_dataset_file_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('user_id');
                $table->bigInteger('file_id');
                $table->string('file_name', 255);
                $table->unique(['file_id'], 'temporary_files_id');
            });
		}
    }

    public function down(): void
    {
		Capsule::schema()->drop('dataverse_studies');
		Capsule::schema()->drop('dataverse_files');
		Capsule::schema()->drop('draft_dataset_files');
	}
}
