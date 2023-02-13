<?php

class DataAPIService
{
    private $client;

    public function __construct(IDataAPIClient $client)
    {
        $this->client = $client;
    }

    public function getDataverseServerName(): string
    {
        $response = $this->client->getDataverseData();

        if ($response->getStatusCode() > 300) {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        $dataverseServerData = json_decode($response->getData());

        return $dataverseServerData->serverName;
    }
}
