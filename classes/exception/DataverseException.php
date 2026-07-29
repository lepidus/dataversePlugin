<?php

namespace APP\plugins\generic\dataverse\classes\exception;

use Exception;

class DataverseException extends Exception
{
    public const AUTHENTICATION_ERROR_CODE = 401;

    public function getUserMessageKey(): string
    {
        return $this->getCode() === self::AUTHENTICATION_ERROR_CODE
            ? 'plugins.generic.dataverse.error.invalidToken'
            : 'plugins.generic.dataverse.error.unavailable';
    }
}
