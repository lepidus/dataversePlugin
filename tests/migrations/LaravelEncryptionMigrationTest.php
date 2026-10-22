<?php

use APP\plugins\generic\dataverse\classes\DataEncryption;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\migrations\DataverseMigration;
use APP\plugins\generic\dataverse\classes\migrations\LaravelEncryptionMigration;
use APP\plugins\generic\dataverse\DataversePlugin;
use APP\plugins\generic\dataverse\DataverseSettingsForm;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use Illuminate\Encryption\Encrypter;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\install\Installer;
use PKP\site\Version;
use PKP\tests\PKPTestCase;
use PKP\xml\PKPXMLParser;

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

    private function storedToken(?int $contextId = null): string
    {
        return DB::table('plugin_settings')->where('plugin_name', 'dataverseplugin')
            ->where('setting_name', 'apiToken')->where('context_id', $contextId ?? $this->context->getId())->value('setting_value');
    }

    private function clearLegacySecret(): void
    {
        $config = &Config::getData();
        $config['security']['api_key_secret'] = '';
    }

    public function testConvertsLegacyTokenAndDaoReadsIt(): void
    {
        $this->storeToken((new DataEncryption())->encryptString('test-api-token'));
        (new LaravelEncryptionMigration())->up();

        $this->assertSame('test-api-token', Crypt::decryptString($this->storedToken()));
        $this->clearLegacySecret();
        $this->assertSame('test-api-token', (new DataverseConfigurationDAO())->get($this->context->getId())->getApiToken());
    }

    public function testEncryptsPlaintextToken(): void
    {
        $this->storeToken('plaintext-api-token');
        (new LaravelEncryptionMigration())->up();

        $this->assertSame('plaintext-api-token', Crypt::decryptString($this->storedToken()));
    }

    public function testInstallMigrationConvertsTokens(): void
    {
        $this->storeToken((new DataEncryption())->encryptString('test-api-token'));
        (new DataverseMigration())->up();

        $this->assertSame('test-api-token', Crypt::decryptString($this->storedToken()));
    }

    public function testConvertsTokensWhenKeyWasWrittenAfterBoot(): void
    {
        $this->storeToken('plaintext-api-token');
        $bootedKey = app('config')->get('app.key');
        $this->setContainerKey('');

        try {
            $this->assertEncrypterRequiresKey();
            (new LaravelEncryptionMigration())->up();
        } finally {
            $this->setContainerKey($bootedKey);
        }

        $this->assertSame('plaintext-api-token', Crypt::decryptString($this->storedToken()));
    }

    private function setContainerKey(?string $key): void
    {
        app('config')->set('app.key', $key);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    private function assertEncrypterRequiresKey(): void
    {
        try {
            Crypt::encryptString('test-api-token');
            $this->fail('The encrypter must not work without the key loaded at boot.');
        } catch (MissingAppKeyException $exception) {
            $this->addToAssertionCount(1);
        }
    }

    public function testFormStoresTokenUsingApplicationKeyWithoutLegacySecret(): void
    {
        $this->clearLegacySecret();
        $plugin = $this->getMockBuilder(DataversePlugin::class)
            ->onlyMethods(['getTemplateResource', 'getName'])->getMock();
        $plugin->method('getName')->willReturn('dataverseplugin');
        $plugin->method('getTemplateResource')->willReturn('dataverseConfigurationForm.tpl');
        $form = new DataverseSettingsForm($plugin, $this->context->getId());
        $form->setData('apiToken', 'test-api-token');
        $form->setData('dataverseUrl', 'https://dataverse.invalid/dataverse/test');
        $form->setData('termsOfUse', ['en' => 'test terms']);
        $form->setData('additionalInstructions', ['en' => 'test instructions']);
        $form->setData('datasetPublish', 2);
        $form->execute();

        $this->assertSame('test-api-token', Crypt::decryptString($this->storedToken()));
        $this->assertSame('test-api-token', (new DataverseConfigurationDAO())->get($this->context->getId())->getApiToken());
    }

    public function testAlreadyConvertedTokenIsUnchangedOnRepeatedMigration(): void
    {
        $encrypted = Crypt::encryptString('test-api-token');
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

    public function testUnconvertibleTokenIsPreservedWithoutStoppingOtherContexts(): void
    {
        $this->storeToken('base64:invalid-payload');
        $otherContext = $this->createTestContext();
        $this->storeToken((new DataEncryption())->encryptString('test-api-token'), $otherContext->getId());

        (new LaravelEncryptionMigration())->up();

        $this->assertSame('base64:invalid-payload', $this->storedToken());
        $this->assertSame('test-api-token', Crypt::decryptString($this->storedToken($otherContext->getId())));
    }

    public function testLegacyTokenIsPreservedWhenLegacySecretIsMissing(): void
    {
        $legacy = (new DataEncryption())->encryptString('test-api-token');
        $this->storeToken($legacy);
        $this->clearLegacySecret();

        (new LaravelEncryptionMigration())->up();

        $this->assertSame($legacy, $this->storedToken());
    }

    public function testTokenEncryptedWithAnotherApplicationKeyIsPreserved(): void
    {
        $encrypter = new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc');
        $encrypted = $encrypter->encryptString('test-api-token');
        $this->storeToken($encrypted);

        (new LaravelEncryptionMigration())->up();

        $this->assertSame($encrypted, $this->storedToken());
    }

    public function testInstallerSelectsMigrationOnlyForVersionsBeforeConversion(): void
    {
        $tree = (new PKPXMLParser())->parse(dirname(__DIR__, 2) . '/upgrade.xml');
        foreach (['3.4.5.1' => 1, '3.4.5.2' => 1, '3.4.5.3' => 0] as $version => $count) {
            $installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
            $installer->currentVersion = Version::fromString($version);
            $installer->actions = [];
            $installer->parseInstallNodes($tree);
            $this->assertCount($count, $installer->actions, $version);
        }
    }
}
