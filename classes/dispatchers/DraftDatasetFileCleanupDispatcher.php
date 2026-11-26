<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFileStorage;
use PKP\plugins\Hook;

class DraftDatasetFileCleanupDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('Submission::delete', [$this, 'deleteSubmissionDraftFiles']);
    }

    public function deleteSubmissionDraftFiles(string $hookName, array $params): void
    {
        $submission = $params[0];
        (new DraftDatasetFileStorage())->deleteSubmission($submission->getData('contextId'), $submission->getId());
    }
}
