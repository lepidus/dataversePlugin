<?php

class DataverseMetadata
{
    static function getDataverseSubjects(): array
	{
		return [
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.agriculturalSciences'),
				'value' => 'Agricultural Sciences',
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.artsAndHumanities'),
				'value' => 'Arts and Humanities'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.astronomyAndAstrophysics'),
				'value' => 'Astronomy and Astrophysics'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.businessAndManagement'),
				'value' => 'Business and Management'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.chemistry'),
				'value' => 'Chemistry'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.computerAndInformationScience'),
				'value' => 'Computer and Information Science'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.earthAndEnvironmentalSciences'),
				'value' => 'Earth and Environmental Sciences'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.Engineering'),
				'value' => 'Engineering'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.Law'),
				'value' => 'Law'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.mathematicalSciences'),
				'value' => 'Mathematical Sciences'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.medicineHealthAndLifeSciences'),
				'value' => 'Medicine, Health and Life Sciences'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.Physics'),
				'value' => 'Physics'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.socialSciences'),
				'value' => 'Social Sciences'
			],
			[
				'label' => __('plugins.generic.dataverse.metadataForm.subject.Other'),
				'value' => 'Other'
			],
		];
	}

	static function getMetadataAttributes($metadata = null): array
	{
		$attributes = [
			'datasetTitle' => [
				'typeName' => 'title',
				'multiple' => false,
				'typeClass' => 'primitive'
			],
			'datasetDescription' => [
				'typeName' => 'dsDescription',
				'multiple' => true,
				'typeClass' => 'compound'
			],
			'datasetKeywords' => [
				'typeName' => 'keyword',
				'multiple' => true,
				'typeClass' => 'compound'
			],
			'datasetSubject' => [
				'typeName' => 'subject',
				'multiple' => true,
				'typeClass' => 'controlledVocabulary'
			],
		];

		return $metadata ? $attributes[$metadata] : $attributes;
	}
}