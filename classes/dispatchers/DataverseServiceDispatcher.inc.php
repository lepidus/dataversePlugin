<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');

class DataverseServiceDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
	{
		HookRegistry::register('Schema::get::submissionFile', array($this, 'modifySubmissionFileSchema'));
		HookRegistry::register('submissionsubmitstep4form::validate', array($this, 'dataverseDepositOnSubmission'));
		HookRegistry::register('Publication::publish', array($this, 'publishDeposit'));

		parent::__construct($plugin);
    }

    public function modifySubmissionFileSchema(string $hookName, array $params): bool
	{
		$schema =& $params[0];
        $schema->properties->{'publishData'} = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
	}

    function dataverseDepositOnSubmission(string $hookName, array $params): void {
		$form =& $params[0];
        $submission = $form->submission;

		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($this->getDataverseConfiguration());
		$service->setSubmission($submission);
		if($service->hasDataSetComponent()){
			$service->depositPackage();
		}
	}

	function publishDeposit(string $hookName, array $params): void {
		$submission = $params[2];

		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($this->getDataverseConfiguration());
		$service->setSubmission($submission);
		$service->releaseStudy();
	}
}
