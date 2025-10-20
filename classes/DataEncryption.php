<?php

namespace APP\plugins\generic\dataverse\classes;

use PKP\config\Config;
use Illuminate\Encryption\Encrypter;
use Exception;

class DataEncryption
{
    private const ENCRYPTION_CIPHER = 'aes-256-cbc';
    private const BASE64_PREFIX = 'base64:';

    public function secretConfigExists(): bool
    {
        try {
            $this->getSecretFromConfig();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    private function getSecretFromConfig(): string
    {
        $secret = Config::getVar('security', 'api_key_secret');
        if ($secret === "") {
            throw new Exception("Dataverse Error: A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted");
        }

        return $this->normalizeSecret($secret);
    }

    private function normalizeSecret(string $secret): string
    {
        return hash('sha256', $secret, true);
    }

    public function textIsEncrypted(string $text): bool
    {
        if (!str_starts_with($text, self::BASE64_PREFIX)) {
            return false;
        }

        try {
            $this->decryptString($text);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function encryptString(string $plainText): string
    {
        $secret = $this->getSecretFromConfig();
        $encrypter = new Encrypter($secret, self::ENCRYPTION_CIPHER);

        try {
            $encryptedString = $encrypter->encrypt($plainText);
        } catch (Exception $e) {
            throw new Exception("DEIA Survey - Failed to encrypt string");
        }

        return self::BASE64_PREFIX . base64_encode($encryptedString);
    }

    public function decryptString(string $encryptedText): string
    {
        $secret = $this->getSecretFromConfig();
        $encrypter = new Encrypter($secret, self::ENCRYPTION_CIPHER);

        $encryptedText = str_replace(self::BASE64_PREFIX, '', $encryptedText);
        $payload = base64_decode($encryptedText);

        try {
            $decryptedString = $encrypter->decrypt($payload);
        } catch (Exception $e) {
            throw new Exception("Dataverse Error: Failed to decrypt string");
        }

        return $decryptedString;
    }
}
