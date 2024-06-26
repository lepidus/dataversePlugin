<?php

namespace APP\plugins\generic\dataverse\classes\dataverseConfiguration;

use PKP\core\DataObject;

class DataverseConfiguration extends DataObject
{
    public const DATASET_PUBLISH_SUBMISSION_ACCEPTED = 1;
    public const DATASET_PUBLISH_SUBMISSION_PUBLISHED = 2;

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

    public function setDatasetPublish(int $datasetPublish): void
    {
        $this->setData('datasetPublish', $datasetPublish);
    }

    public function getDatasetPublish(): ?int
    {
        return $this->getData('datasetPublish');
    }

    public function getDataverseServerUrl(): string
    {
        preg_match(
            '/https:\/\/(.)*?(?=\/)/',
            $this->getDataverseUrl(),
            $matches
        );
        return $matches[0];
    }

    public function getDataverseCollection(): string
    {
        $explodedUrl = explode('/', $this->getDataverseUrl());
        return end($explodedUrl);
    }

    public function getDatasetPublishOptions(): array
    {
        return [
            self::DATASET_PUBLISH_SUBMISSION_ACCEPTED => 'plugins.generic.dataverse.settings.datasetPublish.submissionAccepted',
            self::DATASET_PUBLISH_SUBMISSION_PUBLISHED => 'plugins.generic.dataverse.settings.datasetPublish.submissionPublished'
        ];
    }
}
