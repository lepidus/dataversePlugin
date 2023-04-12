<?php

import('lib.pkp.classes.log.SubmissionLog');
import('classes.log.SubmissionEventLogEntry');

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

    protected function registerAndNotifyError(Submission $submission, string $message, string $error): void
    {
        $request = Application::get()->getRequest();
        $userId = $request->getUser()->getId();

        import('classes.notification.NotificationManager');
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            $userId,
            NOTIFICATION_TYPE_ERROR,
            ['contents' => __($message, ['error' => $error])]
        );

        $this->registerEventLog($submission, $message, ['error' => $error]);

        error_log('Dataverse API error: ' . $error);
    }
}
