<?php

use APP\plugins\generic\dataverse\classes\DataEncryption;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\migrations\LaravelEncryptionMigration;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\tests\PKPTestCase;

class LaravelEncryptionMigrationTest extends PKPTestCase
{
    use CreatesTestContext;

    private $context;
    private $configData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configData = Config::getData();
        $config = &Config::getData();
        $config['security']['api_key_secret'] = 'dataverse-test-legacy-secret';
        DB::beginTransaction();
        DB::table('plugin_settings')->where('plugin_name', 'dataverseplugin')->where('setting_name', 'apiToken')->delete();
        $this->context = $this->createTestContext();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        $config = &Config::getData();
        $config = $this->configData;
        parent::tearDown();
    }

    private function storeToken(string $token, ?int $contextId = null): void
    {
        DB::table('plugin_settings')->insert([
            'plugin_name' => 'dataverseplugin',
            'setting_name' => 'apiToken',
            'setting_value' => $token,
            'context_id' => $contextId ?? $this->context->getId(),
            'setting_type' => 'string',
        ]);
    }

    private function storedToken(): string
    {
        return DB::table('plugin_settings')->where('plugin_name', 'dataverseplugin')
            ->where('setting_name', 'apiToken')->where('context_id', $this->context->getId())->value('setting_value');
    }

    public function testConvertsLegacyTokenAndDaoReadsIt(): void
    {
        $legacy = (new DataEncryption())->encryptString('test-api-token');
        $this->storeToken($legacy);
        (new LaravelEncryptionMigration())->up();

        $this->assertSame('test-api-token', Crypt::decrypt($this->storedToken()));
        $config = &Config::getData();
        $config['security']['api_key_secret'] = '';
        $this->assertSame('test-api-token', (new DataverseConfigurationDAO())->get($this->context->getId())->getApiToken());
    }

    public function testFormStoresTokenUsingApplicationKeyWithoutLegacySecret(): void
    {
        $config = &Config::getData();
        $config['security']['api_key_secret'] = '';
        $plugin = $this->getMockBuilder(\APP\plugins\generic\dataverse\DataversePlugin::class)
            ->onlyMethods(['getTemplateResource', 'getName'])->getMock();
        $plugin->method('getName')->willReturn('dataverseplugin');
        $plugin->method('getTemplateResource')->willReturn('dataverseConfigurationForm.tpl');
        $form = new \APP\plugins\generic\dataverse\DataverseSettingsForm(
            $plugin,
            $this->context->getId()
        );
        $form->setData('apiToken', 'test-api-token');
        $form->setData('dataverseUrl', 'https://dataverse.invalid/dataverse/test');
        $form->setData('termsOfUse', ['en' => 'test terms']);
        $form->setData('additionalInstructions', ['en' => 'test instructions']);
        $form->setData('datasetPublish', 2);
        $form->execute();
        $this->assertSame('test-api-token', Crypt::decrypt($this->storedToken()));
        $this->assertSame('test-api-token', (new DataverseConfigurationDAO())->get($this->context->getId())->getApiToken());
    }

    public function testAlreadyConvertedTokenIsUnchangedOnRepeatedMigration(): void
    {
        $encrypted = Crypt::encrypt('test-api-token');
        $this->storeToken($encrypted);
        (new LaravelEncryptionMigration())->up();
        (new LaravelEncryptionMigration())->up();
        $this->assertSame($encrypted, $this->storedToken());
    }

    public function testEmptyTokenIsUnchanged(): void
    {
        $this->storeToken('');
        (new LaravelEncryptionMigration())->up();
        $this->assertSame('', $this->storedToken());
    }

    public function testFailureRollsBackPreviouslyConvertedTokens(): void
    {
        $legacy = (new DataEncryption())->encryptString('test-api-token');
        $this->storeToken($legacy);
        $otherContext = $this->createTestContext();
        $this->storeToken('base64:invalid-payload', $otherContext->getId());
        try {
            (new LaravelEncryptionMigration())->up();
            $this->fail('Invalid legacy credentials must stop the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('invalid-payload', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertSame($legacy, $this->storedToken());
    }

    public function testUpgradeFromOlderVersionPreservesTokenWhenLegacyKeyIsWrong(): void
    {
        $legacy = (new DataEncryption())->encryptString('test-api-token');
        $this->storeToken($legacy);
        $config = &Config::getData();
        $config['security']['api_key_secret'] = 'wrong-legacy-secret';
        $installer = (new ReflectionClass(\PKP\install\Installer::class))->newInstanceWithoutConstructor();
        $installer->currentVersion = \PKP\site\Version::fromString('3.4.5.1');
        $installer->actions = [];
        $tree = (new \PKP\xml\PKPXMLParser())->parse(dirname(__DIR__, 2) . '/upgrade.xml');
        $installer->parseInstallNodes($tree);
        $this->assertCount(1, $installer->actions);
        $this->assertSame(LaravelEncryptionMigration::class, $installer->actions[0]['attr']['class']);
        $this->expectException(RuntimeException::class);
        try {
            (new LaravelEncryptionMigration())->up();
        } finally {
            $this->assertSame($legacy, $this->storedToken());
        }
    }

    public function testInstallerSelectsMigrationOnlyForVersionsBeforeConversion(): void
    {
        $tree = (new \PKP\xml\PKPXMLParser())->parse(dirname(__DIR__, 2) . '/upgrade.xml');
        foreach (['3.4.5.1' => 1, '3.4.5.2' => 1, '3.4.5.3' => 0] as $version => $count) {
            $installer = (new ReflectionClass(\PKP\install\Installer::class))->newInstanceWithoutConstructor();
            $installer->currentVersion = \PKP\site\Version::fromString($version);
            $installer->actions = [];
            $installer->parseInstallNodes($tree);
            $this->assertCount($count, $installer->actions, $version);
        }
    }

    public function testUnrecognizedTokenIsPreservedAndStopsMigration(): void
    {
        $this->storeToken('unrecognized-token-value');
        $this->expectException(RuntimeException::class);
        try {
            (new LaravelEncryptionMigration())->up();
        } finally {
            $this->assertSame('unrecognized-token-value', $this->storedToken());
        }
    }

    public function testMissingLegacyKeyPreservesTokenAndFails(): void
    {
        $legacy = (new DataEncryption())->encryptString('test-api-token');
        $this->storeToken($legacy);
        $config = &Config::getData();
        $config['security']['api_key_secret'] = '';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('api_key_secret');
        try {
            (new LaravelEncryptionMigration())->up();
        } finally {
            $this->assertSame($legacy, $this->storedToken());
        }
    }
}
