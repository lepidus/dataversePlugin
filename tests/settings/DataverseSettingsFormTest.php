<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\DataverseSettingsForm;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use Illuminate\Support\Facades\DB;
use PKP\tests\DatabaseTestCase;

class DataverseSettingsFormTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->registerPlugin('management/settings');
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private function submitForm(array $data): DataverseSettingsForm
    {
        $_POST = array_merge(['additionalInstructions' => ['en' => '']], $data);
        $this->mockRequest($this->context->getPath() . '/management/settings', $this->user->getId());
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = new DataverseSettingsForm($this->plugin, $this->context->getId());
        $form->readInputData();
        $form->validate();

        return $form;
    }

    public function testFormStartsWithDefaultAdditionalInstructions(): void
    {
        $form = new DataverseSettingsForm($this->plugin, $this->context->getId());
        $form->initData();

        $instructions = $form->getData('additionalInstructions')['en'];
        $this->assertStringContainsString('1. Submit under "Research Data" any files that have been collected', $instructions);
        $this->assertStringContainsString('2. It is mandatory to include a file named "Readme"/"Leiame"/"Leame"', $instructions);
        $this->assertStringContainsString('3. The files deposited in "Research Data" will form a dataset', $instructions);
    }

    public function testDatasetPublishEventIsRequiredOnlyForJournals(): void
    {
        $credentials = $this->dataverseCredentials();

        $errors = $this->submitForm([
            'dataverseUrl' => $credentials['dataverseUrl'],
            'apiToken' => $credentials['apiToken'],
            'termsOfUse' => ['en' => $credentials['termsOfUse']],
        ])->getErrorsArray();

        if (Application::get()->getName() === 'ojs2') {
            $this->assertEquals(__('plugins.generic.dataverse.settings.datasetPublishRequired'), $errors['datasetPublish']);
        } else {
            $this->assertEmpty($errors);
        }
    }

    public function testInvalidApiTokenIsRejectedByDataverse(): void
    {
        $credentials = $this->dataverseCredentials();

        $errors = $this->submitForm([
            'dataverseUrl' => $credentials['dataverseUrl'],
            'apiToken' => 'invalidToken',
            'termsOfUse' => ['en' => $credentials['termsOfUse']],
            'datasetPublish' => 2,
        ])->getErrorsArray();

        $this->assertEquals(
            __('plugins.generic.dataverse.settings.dataverseUrlNotValid', [
                'msg' => __('plugins.generic.dataverse.error.exception.invalidToken'),
            ]),
            $errors['apiToken']
        );
    }

    public function testValidConfigurationIsSaved(): void
    {
        $credentials = $this->dataverseCredentials();

        $form = $this->submitForm([
            'dataverseUrl' => $credentials['dataverseUrl'],
            'apiToken' => $credentials['apiToken'],
            'termsOfUse' => ['en' => $credentials['termsOfUse']],
            'datasetPublish' => 2,
        ]);
        $this->assertTrue($form->isValid());
        $form->execute();

        $configuration = (new DataverseConfigurationDAO())->get($this->context->getId());
        $this->assertEquals($credentials['dataverseUrl'], $configuration->getDataverseUrl());
        $this->assertEquals($credentials['apiToken'], $configuration->getAPIToken());
        $this->assertEquals($credentials['termsOfUse'], $configuration->getLocalizedData('termsOfUse', 'en'));
        $this->assertStringContainsString('1. Submit under "Research Data"', $configuration->getAdditionalInstructions()['en']);

        $storedToken = DB::table('plugin_settings')
            ->where('plugin_name', 'dataverseplugin')
            ->where('context_id', $this->context->getId())
            ->where('setting_name', 'apiToken')
            ->value('setting_value');
        $this->assertNotEquals($credentials['apiToken'], $storedToken);
    }
}
