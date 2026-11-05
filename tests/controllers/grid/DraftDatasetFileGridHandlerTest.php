<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.JSONMessage');
import('classes.core.Request');
import('lib.pkp.classes.core.PKPComponentRouter');
import('lib.pkp.classes.security.authorization.AuthorizationDecisionManager');
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

    public function writeOperations(): array
    {
        return [['addFile', true], ['uploadFile', true], ['saveFile', true], ['deleteFile', true], ['fetchGrid', false]];
    }

    /**
     * @dataProvider writeOperations
     */
    public function testWriteOperationsRequirePublicationEditing(string $operation, bool $isWrite): void
    {
        $policies = [];
        $handler = $this->getMockBuilder(DraftDatasetFileGridHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addPolicy'])
            ->getMock();
        $handler->method('addPolicy')->willReturnCallback(function ($policy) use (&$policies) {
            $policies[] = get_class($policy);
        });
        $handler->_authorizationDecisionManager = $this->createMock(AuthorizationDecisionManager::class);

        $request = $this->createMock(Request::class);
        $request->method('getRequestedOp')->willReturn($operation);
        $request->method('getRouter')->willReturn($this->createMock(PKPComponentRouter::class));

        $args = [];
        $handler->authorize($request, $args, []);

        $this->assertSame($isWrite, in_array('DraftDatasetFileEditPolicy', $policies));
        $this->assertSame($isWrite, in_array('PublicationNotPublishedPolicy', $policies));
    }
}
