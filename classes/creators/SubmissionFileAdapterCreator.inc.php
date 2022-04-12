<?php

import('plugins.generic.dataverse.classes.adapters.SubmissionFileAdapter');

class SubmissionFileAdapterCreator
{
    public function createSubmissionFileAdapter(SubmissionFile $submissionFile): SubmissionFileAdapter
    {
        $locale = $submissionFile->getSubmissionLocale();
        $genreId = $submissionFile->getGenreId();
        $name = $submissionFile->getLocalizedData('name', $locale);
        $path = $submissionFile->getLocalizedData('path', $locale);
        $publishData = $submissionFile->getData('publishData');
        $sponsor = $submissionFile->getLocalizedData('sponsor', $locale) ?? '';

        return new SubmissionFileAdapter($genreId, $name, $path, $publishData, $sponsor);
    }
}

?>