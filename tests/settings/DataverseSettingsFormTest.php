<?php

use APP\core\Request;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\DataversePlugin;
use APP\plugins\generic\dataverse\DataverseSettingsForm;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\session\Session;
use PKP\tests\DatabaseTestCase;

class DataverseSettingsFormTest extends DatabaseTestCase
{
    private const CONTEXT_ID = 9091;
    private const CSRF_TOKEN = 'expectedCsrfToken';
    private const STORED_URL = 'https://demo.dataverse.org/dataverse/example';
    private const STORED_TOKEN = 'storedToken';

    private array $getBackup;
    private array $postBackup;
    private array $serverBackup;
    private $previousConfigurationDAO;

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
        $this->previousConfigurationDAO = DAORegistry::registerDAO('DataverseConfigurationDAO', new DataverseConfigurationDAO());
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_SERVER = $this->serverBackup;
        DAORegistry::registerDAO('DataverseConfigurationDAO', $this->previousConfigurationDAO);
        parent::tearDown();
    }

    private function mockPostRequest(array $postData): void
    {
        $this->simulateRequest('POST', $postData);
    }

    private function simulateRequest(string $method, array $data): void
    {
        $_POST = $method === 'POST' ? $data : [];
        $_GET = $method === 'POST' ? [] : $data;
        $_SERVER['REQUEST_METHOD'] = $method;

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

    private function createFormThatExpectsConfigurationValidation(InvocationOrder $expectedCalls): DataverseSettingsForm
    {
        $form = $this->getMockBuilder(DataverseSettingsForm::class)
            ->setConstructorArgs([$this->createMock(DataversePlugin::class), self::CONTEXT_ID])
            ->onlyMethods(['validateConfiguration'])
            ->getMock();
        $form->expects($expectedCalls)->method('validateConfiguration')->willReturn(true);

        return $form;
    }

    public function requestsThatMustNotReachTheDataverseServer(): array
    {
        return [
            'POST without CSRF token' => ['POST', [], 'form.csrfInvalid'],
            'POST with wrong CSRF token' => ['POST', ['csrfToken' => 'forgedToken'], 'form.csrfInvalid'],
            'GET with the session CSRF token' => ['GET', ['csrfToken' => self::CSRF_TOKEN], 'form.postRequired'],
        ];
    }

    /**
     * @dataProvider requestsThatMustNotReachTheDataverseServer
     */
    public function testRejectedRequestDoesNotContactTheDataverseServer(string $method, array $csrfData, string $errorKey): void
    {
        $this->simulateRequest($method, array_merge($this->validSettings(), $csrfData));
        $form = $this->createFormThatExpectsConfigurationValidation($this->never());
        $form->readInputData();

        $this->assertFalse($form->validate(false));
        $this->assertContains(__($errorKey), $form->getErrorsArray());
    }

    public function testAcceptedRequestValidatesTheConfigurationWithTheDataverseServer(): void
    {
        $this->mockPostRequest(array_merge($this->validSettings(), ['csrfToken' => self::CSRF_TOKEN]));
        $form = $this->createFormThatExpectsConfigurationValidation($this->once());
        $form->readInputData();

        $this->assertTrue($form->validate(false));
    }

    private function storeConfiguration(): void
    {
        $configurationDAO = new DataverseConfigurationDAO();
        $configuration = $configurationDAO->newDataObject();
        $configuration->setDataverseUrl(self::STORED_URL);
        $configuration->setAPIToken(self::STORED_TOKEN);
        $configuration->setTermsOfUse(['en' => 'https://demo.dataverse.org/terms']);
        $configurationDAO->insert(self::CONTEXT_ID, $configuration);
    }

    private function submitWithBlankToken(string $dataverseUrl): DataverseSettingsForm
    {
        return $this->submitForm(array_merge($this->validSettings(), [
            'dataverseUrl' => $dataverseUrl,
            'apiToken' => '',
            'csrfToken' => self::CSRF_TOKEN,
        ]));
    }

    public function testStoredApiTokenIsNotSentBackToTheForm(): void
    {
        $this->storeConfiguration();
        $this->mockPostRequest([]);

        $form = $this->createForm();
        $form->initData();

        $this->assertEmpty($form->getData('apiToken'));
        $this->assertSame(self::STORED_URL, $form->getData('dataverseUrl'));
    }

    public function testBlankApiTokenKeepsTheStoredTokenForTheSameServer(): void
    {
        $this->storeConfiguration();

        $form = $this->submitWithBlankToken('https://demo.dataverse.org/dataverse/otherCollection');

        $this->assertTrue($form->isValid());
        $this->assertSame(self::STORED_TOKEN, $form->getData('apiToken'));
    }

    public function serversThatDoNotReceiveTheStoredToken(): array
    {
        return [
            'another server' => ['https://dataverse.invalid/dataverse/example'],
            'same host without https' => ['http://demo.dataverse.org/dataverse/example'],
            'server whose host starts like the stored one' => ['https://demo.dataverse.org.invalid/dataverse/example'],
        ];
    }

    /**
     * @dataProvider serversThatDoNotReceiveTheStoredToken
     */
    public function testBlankApiTokenIsRejectedWhenTheServerChanges(string $dataverseUrl): void
    {
        $this->storeConfiguration();

        $form = $this->submitWithBlankToken($dataverseUrl);

        $this->assertFalse($form->isValid());
        $this->assertSame('', $form->getData('apiToken'));
        $this->assertContains(__('plugins.generic.dataverse.settings.tokenRequired'), $form->getErrorsArray());
    }
}
