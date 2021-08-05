<?php

class SubmissionAdapterCreator
{
    public function createSubmissionAdapter($submissionId): SubmissionAdapter
    {
        $title = '';
        $authors = array('');
        $description = '';
        $keywords = array('');
        $language = '';

        return new SubmissionAdapter($title, $authors, $description, $keywords, $language);
    }

}
