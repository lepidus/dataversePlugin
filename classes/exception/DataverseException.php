<?php

namespace APP\plugins\generic\dataverse\classes\exception;

use Exception;
use GuzzleHttp\Exception\TransferException;

class DataverseException extends Exception
{
    public const AUTHENTICATION_ERROR_CODE = 401;
    private const SERVICE_UNAVAILABLE_MESSAGE = 'Dataverse service is temporarily unavailable.';
    private const SERVICE_UNAVAILABLE_STATUS = 503;
    private const AUTHENTICATION_ERROR_MESSAGE = 'Dataverse API token is invalid or expired.';

    public static function fromTransferException(TransferException $exception): self
    {
        if (!method_exists($exception, 'hasResponse') || !$exception->hasResponse()) {
            return new self(
                self::SERVICE_UNAVAILABLE_MESSAGE,
                self::SERVICE_UNAVAILABLE_STATUS,
                $exception
            );
        }

        $response = $exception->getResponse();
        $statusCode = $response->getStatusCode();
        $message = self::getJsonErrorMessage($response);

        if (self::isAuthenticationError($statusCode, $message)) {
            return new self(
                self::AUTHENTICATION_ERROR_MESSAGE,
                self::AUTHENTICATION_ERROR_CODE,
                $exception
            );
        }

        if ($message !== null && in_array($statusCode, [400, 404, 409, 422], true)) {
            return new self($message, $statusCode, $exception);
        }

        return new self(
            self::SERVICE_UNAVAILABLE_MESSAGE,
            self::SERVICE_UNAVAILABLE_STATUS,
            $exception
        );
    }

    public function getUserMessageKey(): string
    {
        return $this->getCode() === self::AUTHENTICATION_ERROR_CODE
            ? 'plugins.generic.dataverse.error.invalidToken'
            : 'plugins.generic.dataverse.error.unavailable';
    }

    private static function getJsonErrorMessage($response): ?string
    {
        $responseBody = json_decode((string) $response->getBody(), true);
        if (!is_array($responseBody) || !isset($responseBody['message']) || !is_string($responseBody['message'])) {
            return null;
        }

        return $responseBody['message'];
    }

    private static function isAuthenticationError(int $statusCode, ?string $message): bool
    {
        if ($message === null || !in_array($statusCode, [401, 403], true)) {
            return false;
        }

        if ($statusCode === 401) {
            return true;
        }

        return (bool) preg_match(
            '/(?:bad|invalid|expired).*(?:api key|token)|(?:api key|token).*(?:bad|invalid|expired)/i',
            $message
        );
    }
}
