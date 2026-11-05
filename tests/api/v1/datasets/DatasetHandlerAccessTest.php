<?php

require_once(dirname(__FILE__, 2) . '/CapturesHandlerPolicies.php');

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.APIResponse');
import('lib.pkp.classes.security.Role');
import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
import('classes.submission.Submission');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudy');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');
import('plugins.generic.dataverse.api.v1.datasets.DatasetHandler');

class DatasetHandlerAccessTest extends PKPTestCase
{
    use CapturesHandlerPolicies;

    private const SUBMISSION_ID = 23;
    private const OTHER_SUBMISSION_ID = 99;
    private const STUDY_ID = 7;
    private const SELECTED_FILE_ID = 41;
    private const UNSELECTED_FILE_ID = 42;

    private $studyDAO;
    private $study;
    private $context;

    protected function getMockedRegistryKeys()
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->study = new DataverseStudy();
        $this->study->setId(self::STUDY_ID);
        $this->study->setSubmissionId(self::SUBMISSION_ID);
        $this->study->setPersistentId('doi:10.5072/FK2/TEST');

        $this->studyDAO = $this->createMock(DataverseStudyDAO::class);
        $this->studyDAO->method('getStudy')->with(self::STUDY_ID)->willReturn($this->study);
        DAORegistry::registerDAO('DataverseStudyDAO', $this->studyDAO);

        $this->context = new Journal();
        $request = $this->getMockBuilder(Request::class)->onlyMethods(['getContext'])->getMock();
        $request->method('getContext')->willReturn($this->context);
        Registry::set('request', $request);
    }

    protected function tearDown(): void
    {
        DAORegistry::registerDAO('DataverseStudyDAO', null);
        parent::tearDown();
    }

    private function createHandler(
        ?ReviewAssignment $reviewAssignment = null,
        array $userRoles = [],
        bool $isAssigned = false
    ): DatasetHandler {
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('selectedDataFilesForReview', [self::SELECTED_FILE_ID]);

        $handler = $this->getMockBuilder(DatasetHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthorizedContextObject', 'isAssignedToSubmission'])
            ->getMock();
        $handler->method('getAuthorizedContextObject')->willReturnMap([
            [ASSOC_TYPE_SUBMISSION, $submission],
            [ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment],
            [ASSOC_TYPE_USER_ROLES, $userRoles],
        ]);
        $handler->method('isAssignedToSubmission')->willReturn($isAssigned);

        return $handler;
    }

    private function createSlimRequest()
    {
        $slimRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $slimRequest->method('getQueryParams')->willReturn(['fileId' => self::SELECTED_FILE_ID]);
        $slimRequest->method('getParsedBody')->willReturn([]);

        return $slimRequest;
    }

    private function isFileAvailable(DatasetHandler $handler, int $fileId): bool
    {
        $method = new ReflectionMethod(DatasetHandler::class, 'isFileAvailableToUser');
        $method->setAccessible(true);

        return $method->invoke($handler, $fileId);
    }

    private function createReviewAssignment(?string $dateConfirmed = null): ReviewAssignment
    {
        $reviewAssignment = new ReviewAssignment();
        $reviewAssignment->setDateConfirmed($dateConfirmed);

        return $reviewAssignment;
    }

    public function studyOperations(): array
    {
        return [
            ['get'],
            ['edit'],
            ['disassociateDataset'],
            ['publishDataset'],
            ['addFile'],
            ['getFiles'],
            ['getCitation'],
            ['getInReview'],
            ['deleteFile'],
            ['deleteDataset'],
            ['downloadDatasetFile'],
        ];
    }

    /**
     * @dataProvider studyOperations
     */
    public function testStudyOfAnotherSubmissionIsNotFound(string $operation): void
    {
        $this->study->setSubmissionId(self::OTHER_SUBMISSION_ID);

        $response = $this->createHandler()->$operation(
            $this->createSlimRequest(),
            new APIResponse(),
            ['studyId' => self::STUDY_ID]
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUsersWithoutReviewAssignmentReachAllDatasetFiles(): void
    {
        $this->assertTrue($this->isFileAvailable($this->createHandler(), self::UNSELECTED_FILE_ID));
    }

    public function testReviewerOnlyReachesFilesSelectedForReview(): void
    {
        $handler = $this->createHandler($this->createReviewAssignment());

        $this->assertTrue($this->isFileAvailable($handler, self::SELECTED_FILE_ID));
        $this->assertFalse($this->isFileAvailable($handler, self::UNSELECTED_FILE_ID));
    }

    public function testRestrictedReviewerNeedsToConfirmTheReview(): void
    {
        $this->context->setData('restrictReviewerFileAccess', true);

        $this->assertFalse($this->isFileAvailable(
            $this->createHandler($this->createReviewAssignment()),
            self::SELECTED_FILE_ID
        ));
        $this->assertTrue($this->isFileAvailable(
            $this->createHandler($this->createReviewAssignment('2026-11-05 12:00:00')),
            self::SELECTED_FILE_ID
        ));
    }

    public function testManagerWhoIsAlsoReviewerKeepsAccessToAllFiles(): void
    {
        $handler = $this->createHandler($this->createReviewAssignment(), [ROLE_ID_MANAGER]);

        $this->assertTrue($this->isFileAvailable($handler, self::UNSELECTED_FILE_ID));
    }

    public function testAssignedReviewerKeepsAccessToAllFiles(): void
    {
        $handler = $this->createHandler($this->createReviewAssignment(), [], true);

        $this->assertTrue($this->isFileAvailable($handler, self::UNSELECTED_FILE_ID));
    }

    public function operationPolicies(): array
    {
        $write = 'PublicationCanBeEditedPolicy';
        $decision = 'StageRolePolicy';

        return [
            ['get', []],
            ['getFiles', []],
            ['getCitation', []],
            ['getInReview', []],
            ['downloadDatasetFile', []],
            ['addDataset', [$write]],
            ['addFile', [$write]],
            ['edit', [$write]],
            ['deleteFile', [$write]],
            ['deleteDataset', [$write]],
            ['associateDataset', [$decision]],
            ['disassociateDataset', [$decision]],
            ['publishDataset', [$decision]],
        ];
    }

    /**
     * @dataProvider operationPolicies
     */
    public function testOperationIsAuthorizedAgainstTheSubmission(string $operation, array $operationPolicies): void
    {
        $policies = $this->getPolicyClassesFor(DatasetHandler::class, $operation);

        $this->assertContains('ContextAccessPolicy', $policies);
        $this->assertContains('SubmissionAccessPolicy', $policies);
        $this->assertSame(
            $operationPolicies,
            array_values(array_intersect($policies, ['PublicationCanBeEditedPolicy', 'StageRolePolicy']))
        );
    }
}
