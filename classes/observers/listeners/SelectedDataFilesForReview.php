<?php

namespace APP\plugins\generic\dataverse\classes\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\observers\events\DecisionAdded;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class SelectedDataFilesForReview
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            DecisionAdded::class,
            SelectedDataFilesForReview::class
        );
    }

    public function handle(DecisionAdded $event): void
    {
        $submission = $event->submission;
        $selectedDataFiles = [];

        foreach ($event->actions as $action) {
            if ($action['id'] = 'selectDataFiles') {
                $selectedDataFiles = $action['selectedDataFilesForReview'];
                break;
            }
        }

        if (!empty($selectedDataFiles)) {
            Repo::submission()->edit($submission, [
                'selectedDataFilesForReview' => $selectedDataFiles
            ]);
        }
    }
}
