<?php

require_once(dirname(__FILE__, 2) . '/CapturesHandlerPolicies.php');

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.APIResponse');
import('lib.pkp.classes.security.Role');
import('classes.submission.Submission');
import('lib.pkp.classes.file.TemporaryFile');
import('lib.pkp.classes.file.TemporaryFileDAO');
import('lib.pkp.classes.user.User');
import('classes.core.Request');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFile');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileDAO');
import('plugins.generic.dataverse.api.v1.draftDatasetFiles.DraftDatasetFileHandler');

class DraftDatasetFileHandlerAccessTest extends PKPTestCase
{
    use CapturesHandlerPolicies;

    private const SUBMISSION_ID = 23;
    private const OTHER_SUBMISSION_ID = 99;
    private const USER_ID = 5;
    private const OTHER_USER_ID = 6;
    private const TEMPORARY_FILE_ID = 31;

    private $draftDatasetFileDAO;
    private $temporaryFileDAO;

    protected function getMockedDAOs()
    {
        return ['TemporaryFileDAO'];
    }

    protected function getMockedRegistryKeys()
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->draftDatasetFileDAO = $this->createMock(DraftDatasetFileDAO::class);
        $this->temporaryFileDAO = $this->getMockBuilder(TemporaryFileDAO::class)
            ->onlyMethods(['getTemporaryFile'])
            ->getMock();
        DAORegistry::registerDAO('DraftDatasetFileDAO', $this->draftDatasetFileDAO);
        DAORegistry::registerDAO('TemporaryFileDAO', $this->temporaryFileDAO);

        $user = new User();
        $user->setId(self::USER_ID);
        $request = $this->getMockBuilder(Request::class)->onlyMethods(['getUser'])->getMock();
        $request->method('getUser')->willReturn($user);
        Registry::set('request', $request);

        HookRegistry::register('Schema::get::draftDatasetFile', function ($hookName, $params) {
            $schema = &$params[0];
            $schema = json_decode(file_get_contents(BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/draftDatasetFile.json'));
            return true;
        });
    }

    protected function tearDown(): void
    {
        DAORegistry::registerDAO('DraftDatasetFileDAO', null);
        parent::tearDown();
    }

    private function createHandler(): DraftDatasetFileHandler
    {
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);

        $handler = $this->getMockBuilder(DraftDatasetFileHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthorizedContextObject', 'convertStringsToSchema'])
            ->getMock();
        $handler->method('getAuthorizedContextObject')
            ->with(ASSOC_TYPE_SUBMISSION)
            ->willReturn($submission);
        $handler->method('convertStringsToSchema')->willReturnArgument(1);

        return $handler;
    }

    private function createSlimRequest(array $queryParams = [], array $body = [])
    {
        $slimRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $slimRequest->method('getQueryParams')->willReturn($queryParams);
        $slimRequest->method('getParsedBody')->willReturn($body);

        return $slimRequest;
    }

    private function createDraftDatasetFile(int $submissionId): DraftDatasetFile
    {
        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setId(7);
        $draftDatasetFile->setSubmissionId($submissionId);

        return $draftDatasetFile;
    }

    private function createTemporaryFile(): TemporaryFile
    {
        $temporaryFile = new TemporaryFile();
        $temporaryFile->setId(self::TEMPORARY_FILE_ID);
        $temporaryFile->setUserId(self::USER_ID);
        $temporaryFile->setOriginalFileName('data.csv');

        return $temporaryFile;
    }

    public function testListingIsLimitedToTheAuthorizedSubmission(): void
    {
        $this->draftDatasetFileDAO->expects($this->once())
            ->method('getBySubmissionId')
            ->with(self::SUBMISSION_ID)
            ->willReturn([]);

        $this->createHandler()->getMany(
            $this->createSlimRequest(['submissionId' => self::OTHER_SUBMISSION_ID]),
            new APIResponse(),
            []
        );
    }

    public function testTemporaryFileIsLookedUpForTheCurrentUser(): void
    {
        $this->temporaryFileDAO->expects($this->once())
            ->method('getTemporaryFile')
            ->with(self::TEMPORARY_FILE_ID, self::USER_ID)
            ->willReturn(null);

        $response = $this->createHandler()->add(
            $this->createSlimRequest(
                ['userId' => self::OTHER_USER_ID],
                ['datasetFile' => ['temporaryFileId' => self::TEMPORARY_FILE_ID]]
            ),
            new APIResponse(),
            []
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDraftIsCreatedOnTheAuthorizedSubmission(): void
    {
        $this->temporaryFileDAO->method('getTemporaryFile')->willReturn($this->createTemporaryFile());
        $this->draftDatasetFileDAO->method('newDataObject')->willReturn(new DraftDatasetFile());
        $this->draftDatasetFileDAO->expects($this->once())
            ->method('insertObject')
            ->with($this->callback(function (DraftDatasetFile $draftDatasetFile) {
                return $draftDatasetFile->getSubmissionId() === self::SUBMISSION_ID
                    && $draftDatasetFile->getUserId() === self::USER_ID;
            }))
            ->willReturn(7);

        $this->createHandler()->add(
            $this->createSlimRequest(
                ['submissionId' => self::OTHER_SUBMISSION_ID],
                ['datasetFile' => ['temporaryFileId' => self::TEMPORARY_FILE_ID]]
            ),
            new APIResponse(),
            []
        );
    }

    public function testDraftOfAnotherSubmissionIsNotDeleted(): void
    {
        $this->draftDatasetFileDAO->method('getById')
            ->willReturn($this->createDraftDatasetFile(self::OTHER_SUBMISSION_ID));
        $this->draftDatasetFileDAO->expects($this->never())->method('deleteObject');

        $response = $this->createHandler()->delete(
            $this->createSlimRequest(['fileId' => 7]),
            new APIResponse(),
            []
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDraftOfTheAuthorizedSubmissionIsDeleted(): void
    {
        $this->draftDatasetFileDAO->method('getById')
            ->willReturn($this->createDraftDatasetFile(self::SUBMISSION_ID));
        $this->draftDatasetFileDAO->expects($this->once())->method('deleteObject');

        $response = $this->createHandler()->delete(
            $this->createSlimRequest(['fileId' => 7]),
            new APIResponse(),
            []
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function operationPolicies(): array
    {
        return [
            ['getMany', false],
            ['add', true],
            ['delete', true],
        ];
    }

    /**
     * @dataProvider operationPolicies
     */
    public function testOperationIsAuthorizedAgainstTheSubmission(string $operation, bool $requiresPublicationWrite): void
    {
        $policies = $this->getPolicyClassesFor(DraftDatasetFileHandler::class, $operation);

        $this->assertContains('ContextAccessPolicy', $policies);
        $this->assertContains('SubmissionAccessPolicy', $policies);
        $this->assertSame($requiresPublicationWrite, in_array('PublicationCanBeEditedPolicy', $policies));
        $this->assertSame($requiresPublicationWrite, in_array('PublicationNotPublishedPolicy', $policies));
    }
}
