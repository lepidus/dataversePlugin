<?php

use APP\core\Request;
use APP\plugins\generic\dataverse\DataversePlugin;
use APP\plugins\generic\dataverse\DataverseSettingsForm;
use PKP\core\Registry;
use PKP\session\Session;
use PKP\tests\DatabaseTestCase;

class DataverseSettingsFormTest extends DatabaseTestCase
{
    private const CONTEXT_ID = 9091;
    private const CSRF_TOKEN = 'expectedCsrfToken';

    private array $getBackup;
    private array $postBackup;
    private array $serverBackup;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function getAffectedTables(): array
    {
        return ['plugin_settings'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    private function mockPostRequest(array $postData): void
    {
        $_POST = $postData;
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $session = $this->createMock(Session::class);
        $session->method('getCSRFToken')->willReturn(self::CSRF_TOKEN);

        $request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['getSession', 'getUser', 'getContext'])
            ->getMock();
        $request->method('getSession')->willReturn($session);
        $request->method('getUser')->willReturn(null);
        $request->method('getContext')->willReturn(null);
        Registry::set('request', $request);
    }

    private function createForm(): DataverseSettingsForm
    {
        $form = $this->getMockBuilder(DataverseSettingsForm::class)
            ->setConstructorArgs([$this->createMock(DataversePlugin::class), self::CONTEXT_ID])
            ->onlyMethods(['validateConfiguration'])
            ->getMock();
        $form->method('validateConfiguration')->willReturn(true);

        return $form;
    }

    private function validSettings(): array
    {
        return [
            'dataverseUrl' => 'https://demo.dataverse.org/dataverse/example',
            'apiToken' => 'submittedToken',
            'termsOfUse' => ['en' => 'https://demo.dataverse.org/terms'],
            'additionalInstructions' => ['en' => ''],
            'datasetPublish' => 2,
        ];
    }

    private function submitForm(array $data): DataverseSettingsForm
    {
        $this->mockPostRequest($data);
        $form = $this->createForm();
        $form->readInputData();
        $form->validate(false);

        return $form;
    }

    public function testSubmissionWithoutCsrfTokenIsRejected(): void
    {
        $form = $this->submitForm($this->validSettings());

        $this->assertFalse($form->isValid());
        $this->assertContains(__('form.csrfInvalid'), $form->getErrorsArray());
    }

    public function testSubmissionWithWrongCsrfTokenIsRejected(): void
    {
        $form = $this->submitForm(array_merge($this->validSettings(), ['csrfToken' => 'forgedToken']));

        $this->assertFalse($form->isValid());
        $this->assertContains(__('form.csrfInvalid'), $form->getErrorsArray());
    }

    public function testSubmissionWithTheSessionCsrfTokenIsAccepted(): void
    {
        $form = $this->submitForm(array_merge($this->validSettings(), ['csrfToken' => self::CSRF_TOKEN]));

        $this->assertNotContains(__('form.csrfInvalid'), $form->getErrorsArray());
        $this->assertTrue($form->isValid());
    }
}
