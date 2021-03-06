<?php

import('lib.pkp.classes.notification.PKPNotification');
import('plugins.generic.dataverse.classes.api.DataverseClient');

class DataverseNotificationManager
{
    private $notificationStatusMapping;

    public function __construct()
    {
        $this->notificationStatusMapping = [
            DATAVERSE_PLUGIN_HTTP_STATUS_OK => NOTIFICATION_TYPE_SUCCESS,
            DATAVERSE_PLUGIN_HTTP_STATUS_CREATED => NOTIFICATION_TYPE_SUCCESS,
            DATAVERSE_PLUGIN_HTTP_STATUS_BAD_REQUEST => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_STATUS_UNAUTHORIZED => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_STATUS_FORBIDDEN => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_STATUS_NOT_FOUND => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_STATUS_PRECONDITION_FAILED => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_STATUS_PAYLOAD_TOO_LARGE => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_STATUS_UNSUPPORTED_MEDIA_TYPE => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_STATUS_UNAVAILABLE => NOTIFICATION_TYPE_ERROR,
            DATAVERSE_PLUGIN_HTTP_UNKNOWN_ERROR => NOTIFICATION_TYPE_ERROR,
        ];
    }

    public function getNotificationMessage(int $status, array $params = array()): string
    {
        $notificationMessages = [
            DATAVERSE_PLUGIN_HTTP_STATUS_OK => __('plugins.generic.dataverse.notification.statusPublished', $params),
            DATAVERSE_PLUGIN_HTTP_STATUS_CREATED => __('plugins.generic.dataverse.notification.statusCreated', $params),
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

    public function createNotification(int $dataverseResponseStatus): void
    {
        $user = Application::get()->getRequest()->getUser();
        $dataverseUrl = $this->getDataverseUrl();
        
        $params = ['dataverseUrl' => $dataverseUrl ];

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            $this->notificationStatusMapping[$dataverseResponseStatus],
            array('contents' => $this->getNotificationMessage($dataverseResponseStatus, $params))
        );
    }
}

?>