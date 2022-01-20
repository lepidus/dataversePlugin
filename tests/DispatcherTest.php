<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.DataversePlugin');
import('plugins.generic.dataverse.classes.dispatchers.Dispatcher');


class DispatcherTest extends PKPTestCase
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
		$this->plugin = $this->getMockBuilder(DataversePlugin::class)
            ->setMethods(array('getSetting', 'getName', 'getDisplayName', 'getDescription'))
			->getMock();

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
        $contextId = 1;
        $dispatcher = new Dispatcher($this->plugin);

        $configuration = $dispatcher->getDataverseConfiguration($contextId);

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
