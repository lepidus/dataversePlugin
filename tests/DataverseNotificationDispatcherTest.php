<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dispatchers.DataverseNotificationDispatcher');
import('plugins.generic.dataverse.DataversePlugin');

class DataverseNotificationDispatcherTest extends PKPTestCase
{
    private $dataverseNotification;
    private $dataverseName;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataverseNotification = new DataverseNotificationDispatcher(new DataversePlugin());
        $this->dataverseName = 'Dataverse de Exemplo Lepidus';
    }

    public function testReturnStatusCreatedMessage(): void
    {
        $status = 201;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusCreated##';

        $msg = $this->dataverseNotification->getNotificationMessage($status, $this->dataverseName);
        
        $this->assertEquals($expectedMsg, $msg);
    }

    public function testReturnStatusUnavailableMessage(): void
    {
        $status = 503;
        $expectedMsg = '##plugins.generic.dataverse.notification.statusUnavailable##';

        $msg = $this->dataverseNotification->getNotificationMessage($status, $this->dataverseName);

        $this->assertEquals($expectedMsg, $msg);
    }
}

?>