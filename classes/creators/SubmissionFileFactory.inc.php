<?php

import('plugins.generic.dataverse.classes.adapters.SubmissionFileAdapter');

class SubmissionFileFactory
{
    public function build(SubmissionFile $submissionFile): SubmissionFileAdapter
    {
        $publicFilesDir = Config::getVar('files', 'files_dir');
        $path = $publicFilesDir. $submissionFile->getLocalizedData('path');
        $name = $submissionFile->getLocalizedData('name');
        $publishData = $submissionFile->getData('publishData');

        return new SubmissionFileAdapter($path, $name, $publishData);
    }
}
