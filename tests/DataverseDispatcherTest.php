<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.DataversePlugin');
import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DataverseDispatcherTest extends PKPTestCase
{
    private $dataverseServer = "https://demo.dataverse.org";
    private $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";
    private $apiToken = "APIToken";
    private $plugin;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->registerMockPlugin();
    }
    
    private function registerMockPlugin() {
		$context = $this->getMockBuilder(Context::class)
            ->setMethods(array('getId', 'getAssocType'))
            ->getMock();
        $context->expects($this->any())
                ->method('getId')
                ->will($this->returnValue(1));

        $request = $this->getMockBuilder(PKPRequest::class)
            ->setMethods(array('getContext'))
            ->getMock();
        $request->expects($this->any())
                ->method('getContext')
                ->will($this->returnValue($context));

        $this->plugin = $this->getMockBuilder(DataversePlugin::class)
            ->setMethods(array('getSetting', 'getRequest', 'getName', 'getDisplayName', 'getDescription'))
            ->getMock();

        $this->plugin->expects($this->any())
                     ->method('getRequest')
                     ->will($this->returnValue($request));

        $this->plugin->expects($this->any())
                     ->method('getSetting')
                     ->will($this->returnCallback(array($this, 'getPluginSetting')));
	}

    function getPluginSetting($contextId, $settingName) {
		switch ($settingName) {
			case 'dataverseServer':
				return $this->dataverseServer;

			case 'dataverse':
				return $this->dataverseUrl;

			case 'apiToken':
				return $this->apiToken;

			default:
				self::fail('Required plugin setting is not necessary for the purpose of this test.');
		}
	}

    public function testReturnsTheDataverseConfiguration(): void
    {
        $dispatcher = new DataverseDispatcher($this->plugin);

        $configuration = $dispatcher->getDataverseConfiguration();

        $expectedConfigData = array(
            'dataverseServer' => $this->dataverseServer,
            'dataverseUrl' => $this->dataverseUrl,
            'apiToken' => $this->apiToken,
        );

        $dispatcherConfigData = array(
            'dataverseServer' => $configuration->getDataverseServer(),
            'dataverseUrl' => $configuration->getDataverseUrl(),
            'apiToken' => $configuration->getApiToken(),
        );

        $this->assertEquals($expectedConfigData, $dispatcherConfigData);
    }
}
