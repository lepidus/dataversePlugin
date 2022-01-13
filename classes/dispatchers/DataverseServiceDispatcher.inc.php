<?php

import('plugins.generic.dataverse.classes.DataverseConfiguration');
import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');

class DataverseServiceDispatcher
{
    private $plugin;

    public function __construct(Plugin $plugin)
	{
        $this->plugin = $plugin;
		HookRegistry::register('Schema::get::submissionFile', array($this, 'modifySubmissionFileSchema'));
		HookRegistry::register('submissionsubmitstep4form::validate', array($this, 'dataverseDepositOnSubmission'));
		HookRegistry::register('Publication::publish', array($this, 'publishDeposit'));
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

    public function getDataverseConfiguration(int $contextId): DataverseConfiguration {
		return new DataverseConfiguration(
            $this->plugin->getSetting($contextId, 'apiToken'),
            $this->plugin->getSetting($contextId, 'dataverseServer'),
            $this->plugin->getSetting($contextId, 'dataverse')
        );
	}

    function dataverseDepositOnSubmission(string $hookName, array $params): void {
		$form =& $params[0];
		$context = $form->context;
		$contextId = $context->getId();
        $submission = $form->submission;

		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($this->getDataverseConfiguration($contextId));
		$service->setSubmission($submission);
		if($service->hasDataSetComponent()){
			$service->depositPackage();
		}
	}

	function publishDeposit(string $hookName, array $params): void {
		$submission = $params[2];
		$contextId = $submission->getData("contextId");

		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($this->getDataverseConfiguration($contextId));
		$service->setSubmission($submission);
		$service->releaseStudy();
	}
}
