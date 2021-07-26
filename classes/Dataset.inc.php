<?php

class Dataset
{
    private $title;
    private $creator;
    private $subject;
    private $description;
    private $publisher;
    private $contributor;
    private $date;
    private $type;
    private $source;
    private $relation;
    private $coverage;
    private $license;
    private $rights;
    private $isReferencedBy;

    public function __construct($title, $creator, $subject, $description, $publisher = null, $contributor, $date = null, $type = null, $source = null, $relation = null, $coverage = null, $license = null, $rights = null, $isReferencedBy = null)
    {

        $this->title = $title;
        $this->creator = $creator;
        $this->subject = $subject;
        $this->description = $description;
        $this->publisher = $publisher;
        $this->contributor = $contributor;
        $this->date = $date;
        $this->type = $type;
        $this->source = $source;
        $this->relation = $relation;
        $this->coverage = $coverage;
        $this->license = $license;
        $this->rights = $rights;
        $this->isReferencedBy = $isReferencedBy;
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
