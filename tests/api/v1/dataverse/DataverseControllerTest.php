<?php

use APP\core\Application;
use APP\core\Request as PKPRequest;
use APP\plugins\generic\dataverse\api\v1\dataverse\DataverseController;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\Registry;
use PKP\security\Role;
use PKP\tests\PKPTestCase;
use PKP\user\User;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DataverseControllerTest extends PKPTestCase
{
    private const UNKNOWN_SUBMISSION_ID = 999999999;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $user = new User();
        $user->setId(999999999);
        $pkpRequest = $this->createMock(PKPRequest::class);
        $pkpRequest->method('getUser')->willReturn($user);
        Registry::set('request', $pkpRequest);
    }

    private function canAssociateDataset(array $userRoles): bool
    {
        $submission = new Submission();
        $submission->setId(self::UNKNOWN_SUBMISSION_ID);
        $submission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        $authorizedContext = [
            Application::ASSOC_TYPE_SUBMISSION => $submission,
            Application::ASSOC_TYPE_USER_ROLES => $userRoles,
            Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES => [WORKFLOW_STAGE_ID_SUBMISSION => $userRoles],
        ];
        $controller = $this->getMockBuilder(DataverseController::class)
            ->onlyMethods(['getAuthorizedContext'])
            ->getMock();
        $controller->method('getAuthorizedContext')->willReturn($authorizedContext);

        return (new ReflectionMethod($controller, 'canAssociateDataset'))->invoke($controller);
    }

    public function testAuthorCannotAssociateADataset(): void
    {
        $this->assertFalse($this->canAssociateDataset([Role::ROLE_ID_AUTHOR]));
    }

    public function testManagerNotAssignedToTheSubmissionCanAssociateADataset(): void
    {
        $this->assertTrue($this->canAssociateDataset([Role::ROLE_ID_MANAGER]));
    }
}
