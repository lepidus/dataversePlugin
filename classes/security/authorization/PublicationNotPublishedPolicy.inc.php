<?php

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
import('lib.pkp.classes.submission.PKPSubmission');

class PublicationNotPublishedPolicy extends AuthorizationPolicy
{
    public function effect()
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        if ($submission->getLatestPublication()->getData('status') === STATUS_PUBLISHED) {
            return AUTHORIZATION_DENY;
        }

        return AUTHORIZATION_PERMIT;
    }
}
