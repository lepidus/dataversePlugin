<?php

namespace APP\plugins\generic\dataverse\classes\exception;

use Exception;
use GuzzleHttp\Exception\TransferException;

class DataverseException extends Exception
{
    public const AUTH_ERROR_STATUS_CODE = 401;
    private const UNAVAILABLE_STATUS_CODE = 503;
    private const AUTHENTICATION_ERROR_MESSAGE = 'plugins.generic.dataverse.error.exception.invalidToken';
    private const SERVICE_UNAVAILABLE_MESSAGE = 'plugins.generic.dataverse.error.exception.unavailable';

    public static function fromTransferException(TransferException $exception): self
    {
        if (!method_exists($exception, 'hasResponse') || !$exception->hasResponse()) {
            return new self(
                __(self::SERVICE_UNAVAILABLE_MESSAGE),
                self::UNAVAILABLE_STATUS_CODE,
                $exception
            );
        }

        $response = $exception->getResponse();
        $statusCode = $response->getStatusCode();
        $message = self::getJsonErrorMessage($response);

        if (self::isAuthenticationError($statusCode, $message)) {
            return new self(
                __(self::AUTHENTICATION_ERROR_MESSAGE),
                self::AUTH_ERROR_STATUS_CODE,
                $exception
            );
        }

        if ($message !== null && in_array($statusCode, [400, 404, 409, 422], true)) {
            return new self($message, $statusCode, $exception);
        }

        return new self(
            __(self::SERVICE_UNAVAILABLE_MESSAGE),
            self::UNAVAILABLE_STATUS_CODE,
            $exception
        );
    }

    public function getUserMessageKey(): string
    {
        return $this->getCode() === self::AUTH_ERROR_STATUS_CODE
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
