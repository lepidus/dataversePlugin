<?php

import('lib.pkp.classes.log.SubmissionLog');
import('classes.log.SubmissionEventLogEntry');
import('lib.pkp.classes.log.SubmissionFileEventLogEntry');

abstract class DataverseService
{
    protected function registerEventLog(
        Submission $submission,
        string $message,
        array $params = [],
        int $type = null
    ): void {
        $request = Application::get()->getRequest();

        SubmissionLog::logEvent(
            $request,
            $submission,
            $type ?? SUBMISSION_LOG_METADATA_UPDATE,
            $message,
            $params
        );
    }

    protected function registerAndNotifyError(Submission $submission, string $message, array $params): void
    {
        $request = Application::get()->getRequest();
        $userId = $request->getUser()->getId();

        import('classes.notification.NotificationManager');
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            $userId,
            NOTIFICATION_TYPE_ERROR,
            ['contents' => __($message, $params)]
        );

        $this->registerEventLog($submission, $message, $params);

        error_log('Dataverse API error: ' . $params['error']);
    }
}
