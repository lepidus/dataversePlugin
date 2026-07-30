<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('lib.pkp.classes.mail.MailTemplate');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');

class NotifyDataverseTokenExpiration extends ScheduledTask
{
    private const MANAGER_USER_GROUP_NAME_LOCALE_KEY = 'default.groups.name.manager';

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

        $recipients = $this->getNotificationRecipients($context);
        if (empty($recipients)) {
            return;
        }

        $email = new MailTemplate('DATAVERSE_TOKEN_EXPIRATION', null, $context, false);
        $email->setFrom($context->getData('contactEmail'), $context->getData('contactName'));
        $email->setRecipients($recipients);

        $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
        $email->sendWithParams([
            'contextName' => $context->getLocalizedName(),
            'dataverseName' => $dataverseCollection->getName(),
            'keyExpirationDate' => $tokenExpirationDate
        ]);
    }

    protected function getNotificationRecipients($context): array
    {
        $users = array_merge(
            $this->getUsersByRole(ROLE_ID_SITE_ADMIN, CONTEXT_SITE),
            $this->getJournalManagerUsers($context->getId())
        );

        $recipients = [];
        foreach ($users as $user) {
            $userEmail = strtolower(trim($user->getEmail()));

            if (!isset($recipients[$userEmail])) {
                $recipients[$userEmail] = [
                    'name' => $user->getFullName(),
                    'email' => $userEmail,
                ];
            }
        }

        $contactEmail = $context->getData('contactEmail');
        if (!empty($contactEmail) && !isset($recipients[$contactEmail])) {
            $recipients[$contactEmail] = [
                'name' => $context->getData('contactName'),
                'email' => $contactEmail,
            ];
        }

        return array_values($recipients);
    }

    protected function getUsersByRole(int $roleId, int $contextId): array
    {
        return iterator_to_array(Services::get('user')->getMany([
            'contextId' => $contextId,
            'roleIds' => [$roleId]
        ]));
    }

    protected function getJournalManagerUsers(int $contextId): array
    {
        foreach ($this->getManagerUserGroups($contextId) as $userGroup) {
            if ($userGroup->getData('nameLocaleKey') === self::MANAGER_USER_GROUP_NAME_LOCALE_KEY) {
                return $this->getUsersByUserGroup($userGroup->getId(), $contextId);
            }
        }

        return [];
    }

    protected function getManagerUserGroups(int $contextId): array
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $managerUserGroups = [];

        foreach ($userGroupDao->getByContextId($contextId)->toArray() as $userGroup) {
            if ($userGroup->getRoleId() === ROLE_ID_MANAGER) {
                $managerUserGroups[] = $userGroup;
            }
        }

        return $managerUserGroups;
    }

    protected function getUsersByUserGroup(int $userGroupId, int $contextId): array
    {
        return iterator_to_array(Services::get('user')->getMany([
            'contextId' => $contextId,
            'userGroupIds' => [$userGroupId]
        ]));
    }
}
