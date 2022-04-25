<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.DataversePlugin');
import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DataverseDispatcherTest extends PKPTestCase
{
    protected $dataverseServer = "https://demo.dataverse.org";
    protected $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";
    protected $apiToken = "APIToken";
    protected $plugin;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->registerMockPlugin();
    }
    
    protected function registerMockPlugin(): void
    {
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

    public function getPluginSetting($contextId, $settingName): string
    {
		switch ($settingName) {
			case 'dataverseUrl':
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
            'dataverseUrl' => $this->dataverseUrl,
            'apiToken' => $this->apiToken,
        );

        $dispatcherConfigData = array(
            'dataverseUrl' => $configuration->getDataverseUrl(),
            'apiToken' => $configuration->getApiToken(),
        );

        $this->assertEquals($expectedConfigData, $dispatcherConfigData);
    }
}
