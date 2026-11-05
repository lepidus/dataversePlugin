<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.security.Role');
import('classes.submission.Submission');
import('plugins.generic.dataverse.classes.dispatchers.DatasetTabDispatcher');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFile');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileDAO');

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
            'assistant who is also site admin' => [[ROLE_ID_ASSISTANT, ROLE_ID_SITE_ADMIN], false],
            'manager' => [[ROLE_ID_MANAGER], true],
            'section editor' => [[ROLE_ID_SUB_EDITOR], true],
            'author' => [[ROLE_ID_AUTHOR], true],
            'site admin not enrolled in the journal' => [[ROLE_ID_SITE_ADMIN], false],
            'site admin who is also manager' => [[ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], true],
            'no roles' => [[], false],
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

    private function mockDraftDatasetFiles(int $submissionId): void
    {
        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setData('fileName', 'data.csv');

        $draftDatasetFileDAO = $this->createMock(DraftDatasetFileDAO::class);
        $draftDatasetFileDAO->method('getBySubmissionId')->with($submissionId)->willReturn([$draftDatasetFile]);
        DAORegistry::registerDAO('DraftDatasetFileDAO', $draftDatasetFileDAO);
    }

    public function testDraftFilesAreNotSentToAssistants(): void
    {
        $this->mockDraftDatasetFiles(23);
        $submission = new Submission();
        $submission->setId(23);

        $items = $this->createDispatcher([ROLE_ID_ASSISTANT])->getDraftDatasetFileItems($submission);

        $this->assertSame([], $items);
    }

    public function testDraftFilesAreSentToUsersWithAccess(): void
    {
        $this->mockDraftDatasetFiles(23);
        $submission = new Submission();
        $submission->setId(23);

        $items = $this->createDispatcher([ROLE_ID_AUTHOR])->getDraftDatasetFileItems($submission);

        $this->assertSame('data.csv', $items[0]['fileName']);
    }

    protected function tearDown(): void
    {
        DAORegistry::registerDAO('DraftDatasetFileDAO', null);
        parent::tearDown();
    }
}
