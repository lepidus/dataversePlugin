<?php

define('DATASET_PUBLISH_SUBMISSION_ACCEPTED', 1);
define('DATASET_PUBLISH_SUBMISSION_PUBLISHED', 2);

import('plugins.generic.dataverse.settings.DefaultAdditionalInstructions');

class DataverseConfiguration extends DataObject
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
            DATASET_PUBLISH_SUBMISSION_ACCEPTED => 'plugins.generic.dataverse.settings.datasetPublish.submissionAccepted',
            DATASET_PUBLISH_SUBMISSION_PUBLISHED => 'plugins.generic.dataverse.settings.datasetPublish.submissionPublished'
        ];
    }
}
