<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\install\DowngradeNotSupportedException;

class APITokenEncryptionMigration extends Migration
{
    public function up(): void
    {
        $secret = Config::getVar('security', 'api_key_secret');
        if ($secret === null || $secret === '') {
            return;
        }
        // Keep the historical payload format independent of the runtime encryption class.
        $encrypter = new Encrypter(hash('sha256', $secret, true), 'aes-256-cbc');

        DB::table('plugin_settings')
            ->where('plugin_name', 'dataverseplugin')
            ->where('setting_name', 'apiToken')
            ->get(['context_id', 'setting_value'])
            ->each(function ($row) use ($encrypter) {
                if (empty($row->setting_value) || $this->isEncrypted($row->setting_value, $encrypter)) {
                    return;
                }

                $encryptedValue = 'base64:' . base64_encode($encrypter->encrypt($row->setting_value));
                DB::table('plugin_settings')
                    ->where('plugin_name', 'dataverseplugin')
                    ->where('context_id', $row->context_id)
                    ->where('setting_name', 'apiToken')
                    ->update(['setting_value' => $encryptedValue]);
            });
    }

    private function isEncrypted(string $value, Encrypter $encrypter): bool
    {
        if (!str_starts_with($value, 'base64:')) {
            return false;
        }
        try {
            $encrypter->decrypt(base64_decode(str_replace('base64:', '', $value)));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
