<?php

/**
 * @file classes/migration/DataverseStudyMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataverseStudyMigration
 * @brief Describe database table structures for the Dataverse Study object
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class DataverseStudyMigration extends Migration {
    
    public function up(): void
	{
		if(Capsule::schema()->hasTable('dataverse_studies')) {
			Capsule::schema()->table('dataverse_studies', function (Blueprint $table) {
				$table->text('data_citation')->nullable();
			});
		}
		else {
			Capsule::schema()->create('dataverse_studies', function (Blueprint $table) {
				$table->bigInteger('study_id')->autoIncrement();
				$table->bigInteger('submission_id');
				$table->string('edit_uri', 255);
				$table->string('edit_media_uri', 255);
				$table->string('statement_uri', 255);
				$table->string('persistent_uri', 255);
				$table->text('data_citation')->nullable();
			});
		}
    }
}
