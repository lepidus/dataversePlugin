<?php

class DataverseResponse
{
    private $statusCode;

    private $message;

    private $data;

    public function __construct(int $statusCode, string $message, ?string $data = null)
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->data = $data;
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

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }
}
