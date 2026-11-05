<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.security.Role');
import('plugins.generic.dataverse.classes.dispatchers.DatasetTabDispatcher');

class DatasetTabDispatcherTest extends PKPTestCase
{
    private function createDispatcher(array $userRoles): DatasetTabDispatcher
    {
        $dispatcher = $this->getMockBuilder(DatasetTabDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserRoles'])
            ->getMock();
        $dispatcher->method('getUserRoles')->willReturn($userRoles);

        return $dispatcher;
    }

    public function userRoles(): array
    {
        return [
            'assistant' => [[ROLE_ID_ASSISTANT], false],
            'assistant who is also manager' => [[ROLE_ID_ASSISTANT, ROLE_ID_MANAGER], true],
            'assistant who is also section editor' => [[ROLE_ID_ASSISTANT, ROLE_ID_SUB_EDITOR], true],
            'assistant who is also author' => [[ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR], true],
            'assistant who is also site admin' => [[ROLE_ID_ASSISTANT, ROLE_ID_SITE_ADMIN], true],
            'manager' => [[ROLE_ID_MANAGER], true],
            'section editor' => [[ROLE_ID_SUB_EDITOR], true],
            'author' => [[ROLE_ID_AUTHOR], true],
            'site admin' => [[ROLE_ID_SITE_ADMIN], true],
            'no roles' => [[], true],
        ];
    }

    /**
     * @dataProvider userRoles
     */
    public function testResearchDataIsHiddenOnlyFromUsersWhoAreOnlyAssistants(array $roles, bool $canAccess): void
    {
        $this->assertSame($canAccess, $this->createDispatcher($roles)->canAccessResearchData());
    }

    public function testResearchDataTabIsNotAddedForAssistants(): void
    {
        $dispatcher = $this->createDispatcher([ROLE_ID_ASSISTANT]);
        $output = 'original';
        $templateManager = $this->getMockBuilder(PKPTemplateManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetch'])
            ->getMock();
        $templateManager->expects($this->never())->method('fetch');

        $handled = $dispatcher->addResearchDataTab('Template::Workflow::Publication', [[], $templateManager, &$output]);

        $this->assertFalse($handled);
        $this->assertSame('original', $output);
    }
}
