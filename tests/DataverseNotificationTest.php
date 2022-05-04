<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataverseNotificationManager');
import('plugins.generic.dataverse.DataversePlugin');

class DataverseNotificationManagerTest extends PKPTestCase
{
    private $dataverseNotification;
    private $params;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataverseNotificationMgr = new DataverseNotificationManager();
        $this->params = ['dataverseName' => 'Dataverse de Exemplo Lepidus'];
    }

    public function testReturnStatusCreatedMessage(): void
    {
        $status = 201;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusCreated##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);
        
        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusBadRequestMessage(): void
    {
        $status = 400;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusBadRequest##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusUnauthorizedMessage(): void
    {
        $status = 401;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusUnauthorized##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusForbiddenMessage(): void
    {
        $status = 403;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusForbidden##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusNotFoundMessage(): void
    {
        $status = 404;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusNotFound##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusPreconditionFailedMessage(): void
    {
        $status = 412;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusPreconditionFailed##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusPayloadTooLargeMessage(): void
    {
        $status = 413;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusPayloadTooLarge##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusUnsupportedMediaTypeMessage(): void
    {
        $status = 415;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusUnsupportedMediaType##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusUnavailableMessage(): void
    {
        $status = 503;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusUnavailable##';

        $msg = $this->dataverseNotificationMgr->getNotificationMessage($status, $this->params);

        $this->assertEquals($expectedMsg, $msg);
    }
}

?>