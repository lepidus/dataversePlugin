<?php

class DatasetBuilder
{
    public function build(SubmissionAdapter $submissionAdapter): DatasetModel
    {
        $title = $submissionAdapter->getTitle();
        $creator = $submissionAdapter->getAuthors();
        $description = $submissionAdapter->getDescription();
        $subject = $submissionAdapter->getKeywords();
        
        foreach ($creator as $author) {
            $contributors[] = $author->getAuthorEmail();
        }
        
        return new DatasetModel($title, $creator, $subject, $description, $contributors);
    }
}
