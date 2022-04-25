<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DataverseNotificationDispatcher extends DataverseDispatcher
{
    private $status;

    public function getNotificationMessage(int $status, string $dataverseName): string
    {    
        $params = array('dataverseName' => $dataverseName);

        import('plugins.generic.dataverse.classes.api.DataverseClient');
        $notificationMessages = [
            DATAVERSE_PLUGIN_HTTP_STATUS_CREATED => __('plugins.generic.dataverse.notification.statusCreated', $params),
            DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE => __('plugins.generic.dataverse.notification.statusUnavailable', $params)
        ];

        return $notificationMessages[$status];
    }

    public function sendNotification(int $type): void
    {
        $service = $this->getDataverseService();
        $dataverseName = $service->getDataverseName();
        $user = Application::get()->getRequest()->getUser();

        $notificationManager = new NotificationManager();
        switch ($type) {
            case DATAVERSE_PLUGIN_HTTP_STATUS_CREATED:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_SUCCESS,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_CREATED, $dataverseName)));
                break;
        }
    }
}

?>