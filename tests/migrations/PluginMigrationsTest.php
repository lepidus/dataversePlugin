<?php

use APP\plugins\generic\dataverse\classes\migrations\APITokenEncryptionMigration;
use APP\plugins\generic\dataverse\classes\migrations\DepositMigration;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\install\DowngradeNotSupportedException;
use PKP\tests\DatabaseTestCase;

class PluginMigrationsTest extends DatabaseTestCase
{
    /** @dataProvider irreversibleMigrations */
    public function testDowngradeIsRejectedExplicitly(string $migration): void
    {
        $this->expectException(DowngradeNotSupportedException::class);
        (new $migration())->down();
    }

    public static function irreversibleMigrations(): array
    {
        return [[APITokenEncryptionMigration::class], [DepositMigration::class]];
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testTokenMigrationKeepsItsHistoricalEncryptionWhenRuntimeChanges(): void
    {
        $runtime = Mockery::mock('overload:APP\\plugins\\generic\\dataverse\\classes\\DataEncryption');
        $runtime->shouldReceive('secretConfigExists')->andThrow(new RuntimeException('Runtime encryption changed'));
        $config = &Config::getData();
        $oldSecret = $config['security']['api_key_secret'];
        $config['security']['api_key_secret'] = 'historical-test-secret';
        DB::beginTransaction();
        try {
            DB::table('plugin_settings')->where('plugin_name', 'dataverseplugin')
                ->where('setting_name', 'apiToken')->delete();
            DB::table('plugin_settings')->insert([
                'plugin_name' => 'dataverseplugin', 'context_id' => 1,
                'setting_name' => 'apiToken', 'setting_value' => 'test-api-token', 'setting_type' => 'string',
            ]);
            (new APITokenEncryptionMigration())->up();
            $encrypted = DB::table('plugin_settings')->where('plugin_name', 'dataverseplugin')
                ->where('setting_name', 'apiToken')->value('setting_value');
            $this->assertStringStartsWith('base64:', $encrypted);
            $encrypter = new Encrypter(hash('sha256', 'historical-test-secret', true), 'aes-256-cbc');
            $this->assertSame('test-api-token', $encrypter->decrypt(base64_decode(substr($encrypted, 7))));
            (new APITokenEncryptionMigration())->up();
            $this->assertSame($encrypted, DB::table('plugin_settings')->where('plugin_name', 'dataverseplugin')
                ->where('setting_name', 'apiToken')->value('setting_value'));
        } finally {
            DB::rollBack();
            $config['security']['api_key_secret'] = $oldSecret;
        }
    }
}
