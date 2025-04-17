<?php

namespace APP\plugins\generic\dataverse\classes\entities;

use PKP\core\DataObject;

class DatasetRelatedPublication extends DataObject
{
    public function __construct(string $citation, ?string $idType, ?string $idNumber, ?string $url)
    {
        $this->setData('citation', $citation);
        $this->setData('IDType', $idType);
        $this->setData('IDNumber', $idNumber);
        $this->setData('URL', $url);
    }

    public function getCitation(): string
    {
        return $this->getData('citation');
    }

    public function getIdType(): ?string
    {
        return $this->getData('IDType');
    }

    public function getIdNumber(): ?string
    {
        return $this->getData('IDNumber');
    }

    public function getUrl(): ?string
    {
        return $this->getData('URL');
    }
}
