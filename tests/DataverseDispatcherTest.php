<?php

import('plugins.generic.dataverse.tests.DataverseDispatcherTestCase');
import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DataverseDispatcherTest extends DataverseDispatcherTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDataverseUrl('https://demo.dataverse.org/dataverse/dataverseDeExemplo');
        $this->setApiToken('randomToken');
    }
    public function testReturnsTheDataverseConfiguration(): void
    {
        $dispatcher = new DataverseDispatcher($this->plugin);

        $configuration = $dispatcher->getDataverseConfiguration();

        $expectedConfigData = array(
            'dataverseUrl' => $this->getDataverseUrl(),
            'apiToken' => $this->getApiToken(),
        );

        $dispatcherConfigData = array(
            'dataverseUrl' => $configuration->getDataverseUrl(),
            'apiToken' => $configuration->getApiToken(),
        );

        $this->assertEquals($expectedConfigData, $dispatcherConfigData);
    }
}
