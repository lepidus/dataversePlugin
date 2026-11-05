<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Request');
import('classes.publication.Publication');
import('classes.submission.Submission');
import('plugins.generic.dataverse.classes.security.authorization.DraftDatasetFileEditPolicy');
import('plugins.generic.dataverse.classes.security.authorization.PublicationNotPublishedPolicy');

class DraftDatasetFilePoliciesTest extends PKPTestCase
{
    public function testUnfinishedSubmissionDoesNotDependOnMetadataPermission(): void
    {
        $submission = new Submission();
        $submission->setData('submissionProgress', 2);

        $policy = new DraftDatasetFileEditPolicy($this->createMock(Request::class), 'message');
        $policy->addAuthorizedContextObject(ASSOC_TYPE_SUBMISSION, $submission);

        $this->assertSame(AUTHORIZATION_PERMIT, $policy->effect());
    }

    public function publicationStatuses(): array
    {
        return [
            [STATUS_QUEUED, AUTHORIZATION_PERMIT],
            [STATUS_SCHEDULED, AUTHORIZATION_PERMIT],
            [STATUS_PUBLISHED, AUTHORIZATION_DENY],
        ];
    }

    /**
     * @dataProvider publicationStatuses
     */
    public function testPublishedPublicationIsDenied(int $status, int $effect): void
    {
        $publication = new Publication();
        $publication->setData('status', $status);
        $submission = $this->getMockBuilder(Submission::class)
            ->onlyMethods(['getLatestPublication'])
            ->getMock();
        $submission->method('getLatestPublication')->willReturn($publication);

        $policy = new PublicationNotPublishedPolicy('message');
        $policy->addAuthorizedContextObject(ASSOC_TYPE_SUBMISSION, $submission);

        $this->assertSame($effect, $policy->effect());
    }
}
