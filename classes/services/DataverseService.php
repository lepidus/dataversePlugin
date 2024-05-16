<?php

namespace APP\plugins\generic\dataverse\classes\services;

use APP\submission\Submission;
use APP\core\Application;
use APP\log\event\SubmissionEventLogEntry;
use PKP\core\Core;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\dataverse\classes\facades\Repo;

abstract class DataverseService
{
    protected function registerEventLog(
        Submission $submission,
        string $message,
        array $params = [],
        int $type = null
    ): void {
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => $type ?? SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'message' => __($message, $params),
            'isTranslated' => true,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    protected function registerAndNotifyError(Submission $submission, string $message, array $params): void
    {
        $request = Application::get()->getRequest();
        $userId = $request->getUser()->getId();

        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            $userId,
            Notification::NOTIFICATION_TYPE_ERROR,
            ['contents' => __($message, $params)]
        );

        $this->registerEventLog($submission, $message, $params);
        error_log('Dataverse API error: ' . $params['error']);
    }
}
