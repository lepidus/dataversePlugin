<?php

namespace APP\plugins\generic\dataverse\classes\entities;

class DataverseResponse
{
    private $statusCode;
    private $message;
    private $body;

    public function __construct(int $statusCode, string $message, string $body = null)
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
