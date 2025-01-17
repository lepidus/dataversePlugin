<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('lib.pkp.classes.mail.MailTemplate');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');

class NotifyDataverseTokenExpiration extends ScheduledTask
{
    public function executeActions()
    {
        $dataverseClient = new DataverseClient();
        $tokenExpirationDate = $dataverseClient->getDataverseCollectionActions()->getApiTokenExpirationDate();

        if (empty($tokenExpirationDate)) {
            return false;
        }

        $momentsToSendNotification = ['4 weeks', '3 weeks', '2 weeks', '1 week', '1 day'];
        $today = date('Y-m-d');

        foreach ($momentsToSendNotification as $moment) {
            $momentDate = date('Y-m-d', strtotime($tokenExpirationDate . " -$moment"));

            if ($today == $momentDate) {
                $this->sendNotificationEmail($dataverseClient, $tokenExpirationDate);
                break;
            }
        }

        return true;
    }

    private function sendNotificationEmail($dataverseClient, $tokenExpirationDate)
    {
        $context = Application::get()->getRequest()->getContext();

        $admin = $this->getAdminUser($context->getId());
        if (!$admin) {
            return;
        }

        $email = new MailTemplate('DATAVERSE_TOKEN_EXPIRATION', null, $context, false);
        $email->setFrom($context->getData('contactEmail'), $context->getData('contactName'));
        $email->setRecipients([['name' => $admin->getFullName(), 'email' => $admin->getEmail()]]);

        $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
        $email->sendWithParams([
            'contextName' => $context->getLocalizedName(),
            'dataverseName' => $dataverseCollection->getName(),
            'keyExpirationDate' => $tokenExpirationDate
        ]);
    }

    private function getAdminUser($contextId)
    {
        $applicationName = Application::get()->getName();
        $adminEnAbbrev = ($applicationName == 'ojs2' ? 'jm' : 'psm');

        $adminUserGroup = $this->getUserGroupByAbbrev($contextId, $adminEnAbbrev);
        if (!$adminUserGroup) {
            return null;
        }

        $adminUsers = Services::get('user')->getMany([
            'contextId' => $contextId,
            'userGroupIds' => [$adminUserGroup->getId()]
        ]);

        return $adminUsers->current();
    }

    private function getUserGroupByAbbrev(int $contextId, string $abbrev)
    {
        $contextUserGroups = DAORegistry::getDAO('UserGroupDAO')->getByContextId($contextId)->toArray();

        foreach ($contextUserGroups as $userGroup) {
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en_US'));

            if ($userGroupAbbrev === $abbrev) {
                return $userGroup;
            }
        }

        return null;
    }
}
