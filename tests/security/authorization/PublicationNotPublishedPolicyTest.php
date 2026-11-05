<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\security\authorization\PublicationNotPublishedPolicy;
use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submission\PKPSubmission;
use PKP\tests\PKPTestCase;

class PublicationNotPublishedPolicyTest extends PKPTestCase
{
    #[DataProvider('publicationStatuses')]
    public function testPublishedPublicationIsDenied(int $status, int $effect): void
    {
        $publication = new Publication();
        $publication->setData('status', $status);
        $submission = $this->getMockBuilder(Submission::class)
            ->onlyMethods(['getLatestPublication'])
            ->getMock();
        $submission->method('getLatestPublication')->willReturn($publication);

        $policy = new PublicationNotPublishedPolicy('message');
        $policy->addAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION, $submission);

        $this->assertSame($effect, $policy->effect());
    }

    public static function publicationStatuses(): array
    {
        return [
            [PKPSubmission::STATUS_QUEUED, AuthorizationPolicy::AUTHORIZATION_PERMIT],
            [PKPSubmission::STATUS_SCHEDULED, AuthorizationPolicy::AUTHORIZATION_PERMIT],
            [PKPSubmission::STATUS_PUBLISHED, AuthorizationPolicy::AUTHORIZATION_DENY],
        ];
    }
}
