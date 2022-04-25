<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DataverseNotificationDispatcher extends DataverseDispatcher
{
    private $status;

    public function getNotificationMessage(int $status, string $dataverseName = ''): string
    {    
        $params = array('dataverseName' => $dataverseName);

        import('plugins.generic.dataverse.classes.api.DataverseClient');
        $notificationMessages = [
            DATAVERSE_PLUGIN_HTTP_STATUS_CREATED => __('plugins.generic.dataverse.notification.statusCreated', $params),
            DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE => __('plugins.generic.dataverse.notification.statusUnavailable', $params),
            DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED => __('plugins.generic.dataverse.notification.statusUnauthorized')
        ];

        return $notificationMessages[$status];
    }

    public function sendNotification(int $type): void
    {
        $user = Application::get()->getRequest()->getUser();

        $notificationManager = new NotificationManager();
        switch ($type) {
            case DATAVERSE_PLUGIN_HTTP_STATUS_CREATED:
                $service = $this->getDataverseService();
                $dataverseName = $service->getDataverseName();
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_SUCCESS,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_CREATED, $dataverseName)));
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_WARNING,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED)));
                break;
            default:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR
                );
                break;
        }
    }
}

?>