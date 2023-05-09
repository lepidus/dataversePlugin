<?php

class DataStatement extends DataObject
{
    public function getType(): int
    {
        return $this->getData('type');
    }

    public function setType(int $type): void
    {
        $this->setData('type', $type);
    }

    public function getLinks(): array
    {
        return $this->getData('links');
    }

    public function setLinks(array $links): void
    {
        $this->setData('links', $links);
    }

    public function getDatasetId(): int
    {
        return $this->getData('datasetId');
    }

    public function setDatasetId(int $datasetId): void
    {
        $this->setData('datasetId', $datasetId);
    }

    public function getLocalizedReason()
    {
        return $this->getLocalizedData('reason');
    }

    public function getReason(string $locale): string
    {
        return $this->getData('reason', $locale);
    }

    public function setReason(string $reason, string $locale): void
    {
        $this->setData('reason', $reason, $locale);
    }
}
