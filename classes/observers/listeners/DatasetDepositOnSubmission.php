<?php

namespace APP\plugins\generic\dataverse\classes\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;

class DatasetDepositOnSubmission
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            DatasetDepositOnSubmission::class
        );
    }

    public function handle(SubmissionSubmitted $event): void
    {
        //Actions to deposit dataset on submission
    }
}
