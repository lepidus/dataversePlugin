<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use PKP\core\DataObject;

class DataverseCollection extends DataObject
{
    public function getAlias(): string
    {
        return $this->getData('alias');
    }

    public function setAlias(string $alias): void
    {
        $this->setData('alias', $alias);
    }

    public function getName(): string
    {
        return $this->getData('name');
    }

    public function setName(string $name): void
    {
        $this->setData('name', $name);
    }

    public function getAffiliation(): ?string
    {
        return $this->getData('affiliation');
    }

    public function setAffiliation(string $affiliation): void
    {
        $this->setData('affiliation', $affiliation);
    }

    public function getDataverseContacts(): array
    {
        return $this->getData('dataverseContacts');
    }

    public function setDataverseContacts(array $dataverseContacts): void
    {
        $this->setData('dataverseContacts', $dataverseContacts);
    }

    public function getPermissionRoot(): ?bool
    {
        return $this->getData('permissionRoot');
    }

    public function setPermissionRoot(bool $permissionRoot): void
    {
        $this->setData('permissionRoot', $permissionRoot);
    }

    public function getDescription(): ?string
    {
        return $this->getData('description');
    }

    public function setDescription(string $description): void
    {
        $this->setData('description', $description);
    }

    public function getDataverseType(): ?string
    {
        return $this->getData('dataverseType');
    }

    public function setDataverseType(string $dataverseType): void
    {
        $this->setData('dataverseType', $dataverseType);
    }

    public function getOwnerId(): ?int
    {
        return $this->getData('ownerId');
    }

    public function setOwnerId(int $ownerId): void
    {
        $this->setData('ownerId', $ownerId);
    }

    public function getCreationDate(): ?string
    {
        return $this->getData('creationDate');
    }

    public function setCreationDate(string $creationDate): void
    {
        $this->setData('creationDate', $creationDate);
    }
}
