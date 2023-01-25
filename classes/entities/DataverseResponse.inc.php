<?php

class DataverseResponse
{
    private $statusCode;

    private $message;

    private $body;

    public function __construct(int $statusCode, string $message, array $body)
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): string
    {
        $this->message = $message;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function setBody(array $body): void
    {
        $this->body = $body;
    }
}
