<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.security.Role');
import('plugins.generic.dataverse.classes.dispatchers.DatasetTabDispatcher');

class DatasetTabDispatcherTest extends PKPTestCase
{
    private function createDispatcher(array $accessibleStages): DatasetTabDispatcher
    {
        $dispatcher = $this->getMockBuilder(DatasetTabDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAccessibleWorkflowStages'])
            ->getMock();
        $dispatcher->method('getAccessibleWorkflowStages')->willReturn($accessibleStages);

        return $dispatcher;
    }

    public function accessibleStages(): array
    {
        return [
            'assistant' => [[WORKFLOW_STAGE_ID_EDITING => [ROLE_ID_ASSISTANT]], false],
            'assistant on two stages' => [
                [WORKFLOW_STAGE_ID_EDITING => [ROLE_ID_ASSISTANT], WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_ASSISTANT]],
                false,
            ],
            'assistant who is also author' => [
                [WORKFLOW_STAGE_ID_EDITING => [ROLE_ID_ASSISTANT], WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]],
                true,
            ],
            'assistant who is also section editor' => [
                [WORKFLOW_STAGE_ID_EDITING => [ROLE_ID_ASSISTANT, ROLE_ID_SUB_EDITOR]],
                true,
            ],
            'manager' => [[WORKFLOW_STAGE_ID_EDITING => [ROLE_ID_MANAGER]], true],
            'author' => [[WORKFLOW_STAGE_ID_SUBMISSION => [ROLE_ID_AUTHOR]], true],
            'site admin' => [[WORKFLOW_STAGE_ID_SUBMISSION => [ROLE_ID_SITE_ADMIN]], true],
            'no stages' => [[], true],
        ];
    }

    /**
     * @dataProvider accessibleStages
     */
    public function testResearchDataIsHiddenOnlyFromUsersWhoAreOnlyAssistants(array $stages, bool $canAccess): void
    {
        $this->assertSame($canAccess, $this->createDispatcher($stages)->canAccessResearchData());
    }

    public function testResearchDataTabIsNotAddedForAssistants(): void
    {
        $dispatcher = $this->createDispatcher([WORKFLOW_STAGE_ID_EDITING => [ROLE_ID_ASSISTANT]]);
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
