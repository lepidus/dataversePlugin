<?php

namespace APP\plugins\generic\dataverse\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\mail\Mailable;
use PKP\security\Role;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use Illuminate\Support\Facades\Mail;

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
        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            'DATAVERSE_TOKEN_EXPIRATION'
        );

        $recipients = $this->getNotificationRecipients($context);
        if (empty($recipients)) {
            return;
        }

        $email = new Mailable();
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to($recipients);
        $email->subject($emailTemplate->getLocalizedData('subject'));

        $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
        $emailBody = __('emails.dataverseTokenExpiration.body', [
            'contextName' => $context->getLocalizedName(),
            'dataverseName' => $dataverseCollection->getName(),
            'keyExpirationDate' => $tokenExpirationDate
        ]);
        $email->body($emailBody);

        Mail::send($email);
    }

    protected function getNotificationRecipients($context): array
    {
        $recipients = [];
        $users = array_merge(
            $this->getUsersByRole(Role::ROLE_ID_SITE_ADMIN, Application::CONTEXT_SITE),
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
        return Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([$roleId])
            ->getMany()
            ->toArray();
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
        return Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([Role::ROLE_ID_MANAGER])
            ->filterByIsDefault(true)
            ->getMany()
            ->toArray();
    }

    protected function getUsersByUserGroup(int $userGroupId, int $contextId): array
    {
        return Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByUserGroupIds([$userGroupId])
            ->getMany()
            ->toArray();
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
