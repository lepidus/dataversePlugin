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

    public function getUrl(): string
    {
        return $this->getData('url');
    }

    public function setUrl(string $url): void
    {
        $this->setData('url', $url);
    }

    public function getDatasetId(): int
    {
        return $this->getData('datasetId');
    }

    public function setDatasetId(int $datasetId): void
    {
        $this->setData('datasetId', $datasetId);
    }

    public function getReason(): string
    {
        return $this->getData('reason');
    }

    public function setReason(string $reason): void
    {
        $this->setData('reason', $reason);
    }
}
