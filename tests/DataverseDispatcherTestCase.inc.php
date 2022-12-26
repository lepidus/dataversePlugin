<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.DataversePlugin');

class DataverseDispatcherTestCase extends PKPTestCase
{
    protected $plugin;
    private $dataverseUrl;
    private $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMockPlugin();
    }

    public function getDataverseUrl(): string
    {
        return $this->dataverseUrl;
    }

    public function setDataverseUrl(string $dataverseUrl): void
    {
        $this->dataverseUrl = $dataverseUrl;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): void
    {
        $this->apiToken = $apiToken;
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
}
