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
        $recipients = [];
        $users = array_merge(
            $this->getUsersByRole(ROLE_ID_SITE_ADMIN, CONTEXT_SITE),
            $this->getJournalManagerUsers($context->getId())
        );

        foreach ($users as $user) {
            $this->addRecipient($recipients, $user->getFullName(), $user->getEmail());
        }

        $this->addRecipient(
            $recipients,
            $context->getData('contactName'),
            $context->getData('contactEmail')
        );

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
            if (
                !$userGroup->getDefault()
                || $userGroup->getData('nameLocaleKey') !== self::MANAGER_USER_GROUP_NAME_LOCALE_KEY
            ) {
                continue;
            }

            return $this->getUsersByUserGroup($userGroup->getId(), $contextId);
        }

        return [];
    }

    protected function getManagerUserGroups(int $contextId): array
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $managerUserGroups = [];

        foreach ($userGroupDao->getByContextId($contextId)->toArray() as $userGroup) {
            if ((int) $userGroup->getRoleId() === ROLE_ID_MANAGER) {
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

    private function addRecipient(array &$recipients, ?string $name, ?string $email): void
    {
        $email = trim((string) $email);
        $recipientKey = strtolower($email);
        if ($email === '' || isset($recipients[$recipientKey])) {
            return;
        }

        $recipients[$recipientKey] = [
            'name' => (string) $name,
            'email' => $email,
        ];
    }
}
