<?php

class DatasetModel
{
    private string $title;
    private string $description;
    private array $creator = array();
    private array $subject = array();
    private array $contributor = array();
    private string $publisher;
    private string $date;
    private array $type = array();
    private string $source;
    private string $relation;
    private array $coverage = array();
    private string $license;
    private string $rights;
    private array $isReferencedBy;

    public function __construct(string $title, array $creator, array $subject, string $description, array $contributor, string $publisher = '', string $date = '', array $type = array(), string $source = '', string $relation = '', array $coverage = array(), string $license = '', string $rights = '', array $isReferencedBy = array())
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
        $metadata = array();
        foreach (get_object_vars($this) as $label => $value) {
            if (!empty($value)) {
                $metadata += [$label => $value];
            }
        }
        return $metadata;
    }
}
