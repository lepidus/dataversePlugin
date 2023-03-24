<?php

class DataverseAPIResponse
{
    private $statusCode;

    private $message;

    private $body;

    public function __construct(int $statusCode, string $message, ?string $body = null)
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

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getBodyAsArray(): ?array
    {
        return json_decode($this->body, true);
    }

    public function getBodyAsEntity(string $entityClass): ?DataverseEntity
    {
        if (!is_subclass_of($entityClass, DataverseEntity::class)) {
            throw new InvalidArgumentException('The given class is not a subclass of DataverseEntity');
        }

        $body = $this->getBodyAsArray();
        $data = $body['data'] ?? null;

        if (!$data) {
            return null;
        }

        $entity = new $entityClass();

        if (!$entity->validateData($data)) {
            throw new InvalidArgumentException('The response data is not valid for the given entity');
        }

        $entity->setAllData($data);

        return $entity;
    }
}
