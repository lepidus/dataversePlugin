<?php

import('plugins.generic.dataverse.classes.DatasetModel');

class DatasetFactory
{
    public function build(SubmissionAdapter $submissionAdapter): DatasetModel
    {
        $title = $submissionAdapter->getTitle();
        $authors = $submissionAdapter->getAuthors();
        $files = $submissionAdapter->getFiles();
        $description = $submissionAdapter->getDescription();
        $subject = $submissionAdapter->getKeywords();
        $isReferencedBy = $submissionAdapter->getReference();

        $sponsors = [];
        foreach ($files as $file) {
            if(!empty($file->getData('sponsor')))
                $sponsors[] = $file->getData('sponsor');
        }
        
        if(!empty($sponsors)) {
            foreach ($sponsors as $sponsor) {
                $contributors[] = array('Funder' => $sponsor);
            }
        } else {
            $contributors[] = array('Funder' => 'N/A');
        }
        
        foreach ($authors as $author) {
            $creator[] = $author->getFullName();
        }
        
        return new DatasetModel($title, $creator, $subject, $description, $contributors, '', '', array(), '', '', array(), '', '', $isReferencedBy);
    }
}
