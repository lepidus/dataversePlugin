<?php

import('lib.pkp.classes.security.authorization.internal.PublicationCanBeEditedPolicy');

class DraftDatasetFileEditPolicy extends PublicationCanBeEditedPolicy
{
    public function effect()
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        if ($submission->getData('submissionProgress') > 0) {
            return AUTHORIZATION_PERMIT;
        }

        return parent::effect();
    }
}
