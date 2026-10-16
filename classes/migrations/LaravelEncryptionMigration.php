<?php

namespace APP\plugins\generic\dataverse\classes\migrations;

use APP\plugins\generic\dataverse\classes\DataEncryption;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\PKPAppKey;

class LaravelEncryptionMigration extends Migration
{
    private const LEGACY_PREFIX = 'base64:';

    public function up(): void
    {
        $tokens = $this->getStoredTokens();
        if ($tokens->isEmpty()) {
            return;
        }

        $encrypter = $this->getApplicationEncrypter();
        if ($encrypter === null) {
            error_log('Dataverse: general.app_key is not set, so the stored API tokens were not encrypted with it.');
            return;
        }

        foreach ($tokens as $contextId => $token) {
            $convertedToken = $this->convertToken($token, $encrypter);
            if ($convertedToken === null) {
                error_log("Dataverse: the API token of context {$contextId} could not be converted and must be entered again in the plugin settings.");
                continue;
            }

            if ($convertedToken !== $token) {
                $this->updateToken($contextId, $convertedToken);
            }
        }
    }

    private function getStoredTokens(): Collection
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', 'dataverseplugin')
            ->where('setting_name', 'apiToken')
            ->whereNotNull('setting_value')
            ->where('setting_value', '<>', '')
            ->pluck('setting_value', 'context_id');
    }

    private function updateToken(int $contextId, string $token): void
    {
        DB::table('plugin_settings')
            ->where('plugin_name', 'dataverseplugin')
            ->where('setting_name', 'apiToken')
            ->where('context_id', $contextId)
            ->update(['setting_value' => $token]);
    }

    private function getApplicationEncrypter(): ?Encrypter
    {
        $appKey = Config::reloadData()['general']['app_key'] ?? null;
        if (empty($appKey)) {
            return null;
        }

        return new Encrypter(PKPAppKey::parseKey($appKey), PKPAppKey::getCipher());
    }

    private function convertToken(string $token, Encrypter $encrypter): ?string
    {
        if ($this->isEncryptedWith($token, $encrypter)) {
            return $token;
        }

        if (str_starts_with($token, self::LEGACY_PREFIX)) {
            return $this->convertLegacyToken($token, $encrypter);
        }

        if ($this->isEncryptedPayload($token)) {
            return null;
        }

        return $encrypter->encryptString($token);
    }

    private function convertLegacyToken(string $token, Encrypter $encrypter): ?string
    {
        try {
            return $encrypter->encryptString((new DataEncryption())->decryptString($token));
        } catch (Exception $exception) {
            return null;
        }
    }

    private function isEncryptedWith(string $token, Encrypter $encrypter): bool
    {
        try {
            $encrypter->decryptString($token);
            return true;
        } catch (DecryptException $exception) {
            return false;
        }
    }

    private function isEncryptedPayload(string $token): bool
    {
        $payload = json_decode(base64_decode($token, true) ?: '', true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
