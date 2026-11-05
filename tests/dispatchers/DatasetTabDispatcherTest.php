<?php

use APP\plugins\generic\dataverse\classes\dispatchers\DatasetTabDispatcher;
use APP\template\TemplateManager;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

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

    /**
     * @dataProvider userRoles
     */
    public function testResearchDataFollowsTheRolesOfTheApi(array $roles, bool $canAccess): void
    {
        $this->assertSame($canAccess, $this->createDispatcher($roles)->canAccessResearchData());
    }

    public static function userRoles(): array
    {
        return [
            'assistant' => [[Role::ROLE_ID_ASSISTANT], false],
            'assistant who is also manager' => [[Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_MANAGER], true],
            'assistant who is also section editor' => [[Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_SUB_EDITOR], true],
            'assistant who is also author' => [[Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR], true],
            'manager' => [[Role::ROLE_ID_MANAGER], true],
            'section editor' => [[Role::ROLE_ID_SUB_EDITOR], true],
            'author' => [[Role::ROLE_ID_AUTHOR], true],
            'site admin not enrolled in the journal' => [[Role::ROLE_ID_SITE_ADMIN], false],
            'no roles' => [[], false],
        ];
    }

    public function testResearchDataTabIsNotAddedForAssistants(): void
    {
        $output = 'original';
        $templateManager = $this->getMockBuilder(TemplateManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetch'])
            ->getMock();
        $templateManager->expects($this->never())->method('fetch');

        $handled = $this->createDispatcher([Role::ROLE_ID_ASSISTANT])
            ->addResearchDataTab('Template::Workflow::Publication', [[], $templateManager, &$output]);

        $this->assertFalse($handled);
        $this->assertSame('original', $output);
    }
}
