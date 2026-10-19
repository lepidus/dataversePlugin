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

    public function getAdditionalInstructions(): array
    {
        if ($this->additionalInstructionsAreEmpty()) {
            $defaultAdditionalInstructions = new DefaultAdditionalInstructions();
            return $defaultAdditionalInstructions->getDefaultInstructions();
        }

        return $this->getData('additionalInstructions');
    }

    public function getLocalizedAdditionalInstructions(): string
    {
        if ($this->additionalInstructionsAreEmpty()) {
            $defaultAdditionalInstructions = new DefaultAdditionalInstructions();
            $this->setData('additionalInstructions', $defaultAdditionalInstructions->getDefaultInstructions());
        }

        return $this->getLocalizedData('additionalInstructions');
    }

    public function additionalInstructionsAreEmpty(): bool
    {
        if (is_null($this->getData('additionalInstructions'))) {
            return true;
        }

        foreach ($this->getData('additionalInstructions') as $locale => $value) {
            if (!empty($value)) {
                return false;
            }
        }

        return true;
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
        $parts = parse_url($this->getDataverseUrl());
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $isLocalHttp = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true);
        if (!$host || ($scheme !== 'https' && !$isLocalHttp)) {
            throw new \InvalidArgumentException(
                'Dataverse URL must use HTTPS, or HTTP with localhost or 127.0.0.1.'
            );
        }
        $serverUrl = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $serverUrl .= ':' . $parts['port'];
        }
        return $serverUrl;
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
