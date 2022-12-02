<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DatasetSubjectDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
	{
        HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'addSubjectField'));
        HookRegistry::register('submissionsubmitstep3form::validate', array($this, 'readSubjectField'));

		parent::__construct($plugin);
	}

    public function addSubjectField($hookName, $args): void
	{
		$templateMgr =& $args[1];
		$output = &$args[2];

		$submissionId = $templateMgr->get_template_vars('submissionId');
		$submission = Services::get('submission')->get($submissionId);

		$draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
		$draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($submissionId);

		if (!empty($draftDatasetFiles)) {
			$dataverseSubjectVocab = $this->getDataverseSubjectVocab();
            $datasetSubjectLabels = array_column($dataverseSubjectVocab, 'label');
            $datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

			$templateMgr->assign([
				'dataverseSubjectVocab' => $datasetSubjectLabels,
				'subjectId' => array_search($submission->getData('datasetSubject'), $datasetSubjectValues)
			]);

			$output .= $templateMgr->fetch($this->plugin->getTemplateResource('subjectField.tpl'));
		}
	}

    public function readSubjectField($hookName, $args): bool
	{
		$form =& $args[0];
		$submission =& $form->submission;
		
        $form->readUserVars(array('datasetSubject'));
		$subject = $form->getData('datasetSubject');

		$dataverseSubjectVocab = $this->getDataverseSubjectVocab();
		$datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

		Services::get('submission')->edit(
			$submission,
			['datasetSubject' => $datasetSubjectValues[$subject]],
			Application::get()->getRequest()
		);

		return false;
	}

	private function getDataverseSubjectVocab(): array
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
}