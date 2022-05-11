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

    public function __construct(string $title, array $creator, array $subject, string $description, array $contributor, string $publisher = '', string $date = '', array $type = array(), string $source = '', string $relation = '', array $coverage = array(), string $license = '', string $rights = '', array $isReferencedBy = array())
    {
        $this->title = $title;
        $this->description = $description;
        $this->creator = $creator;
        $this->subject = $this->validateSubject($subject);
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
        $metadata = array();
        foreach (get_object_vars($this) as $label => $value) {
            if (!empty($value)) {
                $metadata += [$label => $value];
            }
        }
        return $metadata;
    }

    private function validateSubject($subject)
    {
        if(empty($subject) || $subject[0] == "") { 
            return array("N/A");
        }
        return $subject;
    }
}
