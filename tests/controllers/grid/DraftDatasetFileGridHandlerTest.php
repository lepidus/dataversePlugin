<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.JSONMessage');
import('classes.core.Request');
import('classes.submission.Submission');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFile');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileDAO');
import('plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridHandler');

class DraftDatasetFileGridHandlerTest extends PKPTestCase
{
    private const SUBMISSION_ID = 23;
    private const OTHER_SUBMISSION_ID = 99;

    protected function tearDown(): void
    {
        DAORegistry::registerDAO('DraftDatasetFileDAO', null);
        parent::tearDown();
    }

    public function testDraftOfAnotherSubmissionIsNotDeleted(): void
    {
        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setSubmissionId(self::OTHER_SUBMISSION_ID);

        $draftDatasetFileDAO = $this->createMock(DraftDatasetFileDAO::class);
        $draftDatasetFileDAO->method('getById')->with(7)->willReturn($draftDatasetFile);
        $draftDatasetFileDAO->expects($this->never())->method('deleteById');
        DAORegistry::registerDAO('DraftDatasetFileDAO', $draftDatasetFileDAO);

        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $handler = $this->getMockBuilder(DraftDatasetFileGridHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSubmission'])
            ->getMock();
        $handler->method('getSubmission')->willReturn($submission);

        $request = $this->createMock(Request::class);
        $request->method('checkCSRF')->willReturn(true);

        $result = $handler->deleteFile(['fileId' => '7', 'fileName' => 'data.csv'], $request);

        $this->assertFalse($result->getStatus());
    }
}
