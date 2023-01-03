<?php

class DataverseResponse
{
    private $statusCode;
    private $content;
    private $message;

    public function __construct(string $statusCode, ?array $content = null, ?string $message = null)
    {
        $this->statusCode = $statusCode;
        $this->content = $content;
        $this->message = $message;
    }

    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}