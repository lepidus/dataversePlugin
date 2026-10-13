<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use APP\plugins\generic\dataverse\classes\DataEncryption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class LaravelEncryptionMigration extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $settings = DB::table('plugin_settings')
                ->where('plugin_name', 'dataverseplugin')
                ->where('setting_name', 'apiToken')
                ->orderBy('context_id')
                ->get(['context_id', 'setting_value']);

            foreach ($settings as $row) {
                if ($row->setting_value === null || $row->setting_value === '') {
                    continue;
                }

                try {
                    if (!str_starts_with($row->setting_value, 'base64:')) {
                        // A repeated migration must preserve credentials already using the application key.
                        Crypt::decrypt($row->setting_value);
                        continue;
                    }

                    $token = (new DataEncryption())->decryptString($row->setting_value);
                    $encryptedToken = Crypt::encrypt($token);
                } catch (Throwable $exception) {
                    // Do not attach the original exception: it may contain sensitive payloads.
                    throw new RuntimeException(
                        'Dataverse credential migration failed. Check general.app_key and the original '
                        . 'security.api_key_secret before retrying. Stored credentials have not been changed.'
                    );
                }

                DB::table('plugin_settings')
                    ->where('plugin_name', 'dataverseplugin')
                    ->where('setting_name', 'apiToken')
                    ->where('context_id', $row->context_id)
                    ->update(['setting_value' => $encryptedToken]);
            }
        });
    }
}
