<?php

class DataverseNotificationManager
{
    public function getNotificationMessage(int $status, array $params = array()): string
    {
        import('plugins.generic.dataverse.classes.api.DataverseClient');
        $notificationMessages = [
            DATAVERSE_PLUGIN_HTTP_STATUS_CREATED => __('plugins.generic.dataverse.notification.statusCreated', $params),
            DATAVERSE_PLUGIN_HTTP_STATUS_OK => __('plugins.generic.dataverse.notification.statusPublished', $params),
            DATAVERSE_PLUGIN_HTTP_STATUS_BAD_REQUEST => __('plugins.generic.dataverse.notification.statusBadRequest'),
            DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED => __('plugins.generic.dataverse.notification.statusUnauthorized'),
            DATAVERSE_PLUGIN_HTTP_STATUS_FORBIDDEN => __('plugins.generic.dataverse.notification.statusForbidden', $params),
            DATAVERSE_PLUGIN_HTTP_STATUS_NOT_FOUND => __('plugins.generic.dataverse.notification.statusNotFound'),
            DATAVERSE_PLUGIN_HTTP_STATUS_PRECONDITION_FAILED => __('plugins.generic.dataverse.notification.statusPreconditionFailed'),
            DATAVERSE_PLUGIN_HTTP_STATUS_PAYLOAD_TOO_LARGE => __('plugins.generic.dataverse.notification.statusPayloadTooLarge'),
            DATAVERSE_PLUGIN_HTTP_STATUS_UNSUPPORTED_MEDIA_TYPE => __('plugins.generic.dataverse.notification.statusUnsupportedMediaType'),
            DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE => __('plugins.generic.dataverse.notification.statusUnavailable', $params),
            DATAVERSE_PLUGIN_HTTP_UNKNOWN_ERROR => __('plugins.generic.dataverse.notification.unknownError', $params),
        ];

        return $notificationMessages[$status];
    }

    public function getDataverseUrl(): string
    {
        $request = PKPApplication::get()->getRequest();
        $contextId = $request->getContext()->getId();

        $dataverseDAO = DAORegistry::getDAO('DataverseDAO');
        $credentials = $dataverseDAO->getCredentialsFromDatabase($contextId);
        $dataverseUrl = $credentials[1];
        return $dataverseUrl;
    }

    public function sendNotification(int $type, array $params = array()): void
    {
        $user = Application::get()->getRequest()->getUser();
        
        $dataverseUrl = $this->getDataverseUrl();
        $params['dataverseUrl'] = $dataverseUrl;

        $notificationManager = new NotificationManager();
        switch ($type) {
            case DATAVERSE_PLUGIN_HTTP_STATUS_CREATED:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_SUCCESS,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_CREATED, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_OK:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_SUCCESS,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_OK, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_BAD_REQUEST:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_CREATED, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_FORBIDDEN:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_FORBIDDEN, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_NOT_FOUND:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_NOT_FOUND, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_PRECONDITION_FAILED:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_PRECONDITION_FAILED, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_PAYLOAD_TOO_LARGE:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_PAYLOAD_TOO_LARGE, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_UNSUPPORTED_MEDIA_TYPE:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_UNSUPPORTED_MEDIA_TYPE, $params))
                );
                break;
            case DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE, $params))
                );
                break;
            default:
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_ERROR,
                    array('contents' => $this->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_UNKNOWN_ERROR, $params))
                );
                break;
        }
    }
}

?>