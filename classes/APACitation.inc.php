<?php

class APACitation
{
    private DataverseStudy $study;

    public function __construct(DataverseStudy $study)
    {
        $this->study = $study;
    }

    public function asMarkup(): String
    {
        $href = '<a href="'. $this->study->getPersistentUri() .'">'. $this->study->getPersistentUri() .'</a>';
        return str_replace($this->study->getPersistentUri(), $href, strip_tags($this->study->getDataCitation()));
    }
}