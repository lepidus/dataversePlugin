<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\dataverse\classes\DataEncryption;

class APITokenEncryptionMigration extends Migration
{
    public function up(): void
    {
        $encrypter = new DataEncryption();
        if (!$encrypter->secretConfigExists()) {
            return;
        }

        DB::table('plugin_settings')
            ->where('plugin_name', 'dataverseplugin')
            ->where('setting_name', 'apiToken')
            ->get(['context_id', 'setting_value'])
            ->each(function ($row) use ($encrypter) {
                if (empty($row->setting_value) || $encrypter->textIsEncrypted($row->setting_value)) {
                    return;
                }

                $encryptedValue = $encrypter->encryptString($row->setting_value);
                DB::table('plugin_settings')
                    ->where('plugin_name', 'dataverseplugin')
                    ->where('context_id', $row->context_id)
                    ->where('setting_name', 'apiToken')
                    ->update(['setting_value' => $encryptedValue]);
            });
    }

}
