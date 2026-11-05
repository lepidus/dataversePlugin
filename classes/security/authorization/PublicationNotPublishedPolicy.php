<?php

namespace APP\plugins\generic\dataverse\classes\security\authorization;

use APP\core\Application;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submission\PKPSubmission;

class PublicationNotPublishedPolicy extends AuthorizationPolicy
{
    public function effect()
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if ($submission->getLatestPublication()->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}
