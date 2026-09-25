<?php

use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use PKP\plugins\Hook;
use PKP\tests\DatabaseTestCase;

class DataversePluginRegistrationTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private const SUBMISSION_AND_WORKFLOW_HOOKS = [
        'Template::SubmissionWizard::Section',
        'Submission::validateSubmit',
        'TemplateManager::display',
        'Schema::get::publication',
    ];

    private function countPluginCallbacks(): array
    {
        $counts = [];
        foreach (self::SUBMISSION_AND_WORKFLOW_HOOKS as $hookName) {
            $counts[$hookName] = 0;
            foreach (Hook::getHooks($hookName) ?? [] as $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback) && str_starts_with(get_class($callback[0]), 'APP\plugins\generic\dataverse\\')) {
                        $counts[$hookName]++;
                    }
                }
            }
        }

        return $counts;
    }

    public function testUnconfiguredPluginLeavesSubmissionAndWorkflowUntouched(): void
    {
        $callbacksBeforeRegistration = $this->countPluginCallbacks();

        $this->registerPlugin('dashboard/editorial');

        $this->assertEquals($callbacksBeforeRegistration, $this->countPluginCallbacks());
    }

    public function testConfiguredPluginExtendsSubmissionAndWorkflow(): void
    {
        $this->configureWithPlaceholderDataverse();
        $callbacksBeforeRegistration = $this->countPluginCallbacks();

        $this->registerPlugin('dashboard/editorial');

        foreach ($this->countPluginCallbacks() as $hookName => $count) {
            $this->assertGreaterThan($callbacksBeforeRegistration[$hookName], $count, $hookName);
        }
        $this->assertObjectHasProperty('dataStatementTypes', app()->get('schema')->get('publication')->properties);
    }
}
