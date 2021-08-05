<?php

class DatasetModel
{
    private $title;
    private $description;
    private $creator = array();
    private $subject = array();
    private $contributor = array();
    private $publisher;
    private $date;
    private $type = array();
    private $source;
    private $relation;
    private $coverage = array();
    private $license;
    private $rights;
    private $isReferencedBy;

    public function __construct(string $title, array $creator, array $subject, string $description, array $contributor, string $publisher = null, string $date = null, array $type = null, string $source = null, string $relation = null, array $coverage = null, string $license = null, string $rights = null, string $isReferencedBy = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->creator = $creator;
        $this->subject = $subject;
        $this->publisher = $publisher;
        $this->contributor = $contributor;
        $this->date = $date ? strftime('%Y-%m-%d', strtotime($date)) : $date;
        $this->type = $type;
        $this->source = $source;
        $this->relation = $relation;
        $this->coverage = $coverage;
        $this->license = $license;
        $this->rights = $rights;
        $this->isReferencedBy = $isReferencedBy;
    }

    public function getMetadataValues(): array
    {
        $validMetadata = array();
        foreach (get_object_vars($this) as $label => $value) {
            if (isset($value)) {
                $validMetadata += [$label => $value];
            }
        }
        return $validMetadata;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getCreator()
    {
        return $this->creator;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getPublisher()
    {
        return $this->publisher;
    }

    public function getContributor()
    {
        return $this->contributor;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getRelation()
    {
        return $this->relation;
    }

    public function getCoverage()
    {
        return $this->coverage;
    }

    public function getLicense()
    {
        return $this->license;
    }

    public function getRights()
    {
        return $this->contributor;
    }

    public function getIsReferencedBy()
    {
        return $this->isReferencedBy;
    }
}
