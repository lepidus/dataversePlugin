<?php

class DataverseCredentials extends DataObject
{
    public function setDataverseUrl(string $url): void
    {
        $this->setData('dataverseUrl', $url);
    }

    public function getDataverseUrl(): string
    {
        return $this->getData('dataverseUrl');
    }

    public function setAPIToken(string $apiToken): void
    {
        $this->setData('apiToken', $apiToken);
    }

    public function getAPIToken(): string
    {
        return $this->getData('apiToken');
    }

    public function setTermsOfUse(array $termsOfUse): void
    {
        $this->setData('termsOfUse', $termsOfUse);
    }

    public function getTermsOfUse(): array
    {
        return $this->getData('termsOfUse');
    }
}
