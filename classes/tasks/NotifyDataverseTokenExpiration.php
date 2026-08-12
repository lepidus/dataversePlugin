<?php

namespace APP\plugins\generic\dataverse\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\mail\Mailable;
use PKP\security\Role;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;
use PKP\userGroup\UserGroup;

class NotifyDataverseTokenExpiration extends ScheduledTask
{
    private const MANAGER_USER_GROUP_NAME_LOCALE_KEY = 'default.groups.name.manager';

    protected function executeActions(): bool
    {
        $configurationDAO = DAORegistry::getDAO('DataverseConfigurationDAO')
            ?? DAORegistry::registerDAO('DataverseConfigurationDAO', new DataverseConfigurationDAO());

        $contexts = Application::getContextDAO()->getAll(true);

        while ($context = $contexts->next()) {
            if (!$configurationDAO->hasConfiguration($context->getId())) {
                continue;
            }

            $this->notifyContext($context, $configurationDAO->get($context->getId()));
        }

        return true;
    }

    protected function notifyContext($context, DataverseConfiguration $configuration): void
    {
        try {
            $collectionActions = new DataverseCollectionActions($configuration);
            $tokenExpirationDate = $collectionActions->getApiTokenExpirationDate();

            if (empty($tokenExpirationDate)) {
                return;
            }

            $momentsToSendNotification = ['4 weeks', '3 weeks', '2 weeks', '1 week', '1 day'];
            $today = date('Y-m-d');

            foreach ($momentsToSendNotification as $moment) {
                $momentDate = date('Y-m-d', strtotime($tokenExpirationDate . " -$moment"));

                if ($today == $momentDate) {
                    $this->sendNotificationEmail($collectionActions, $tokenExpirationDate, $context);
                    break;
                }
            }
        } catch (DataverseException $exception) {
            error_log('Dataverse token expiration check unavailable (HTTP ' . $exception->getCode() . ')');
        }
    }

    private function sendNotificationEmail($collectionActions, $tokenExpirationDate, $context)
    {
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

        $dataverseCollection = $collectionActions->get();
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
        $users = array_merge(
            $this->getUsersByRole(Role::ROLE_ID_SITE_ADMIN, Application::SITE_CONTEXT_ID),
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

    protected function getUsersByRole(int $roleId, ?int $contextId): array
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
            if ($userGroup->nameLocaleKey === self::MANAGER_USER_GROUP_NAME_LOCALE_KEY) {
                return $this->getUsersByUserGroup($userGroup->id, $contextId);
            }
        }

        return [];
    }

    protected function getManagerUserGroups(int $contextId): array
    {
        return UserGroup::query()
            ->withContextIds([$contextId])
            ->withRoleIds([Role::ROLE_ID_MANAGER])
            ->isDefault(true)
            ->get()
            ->all();
    }

    protected function getUsersByUserGroup(int $userGroupId, int $contextId): array
    {
        return Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByUserGroupIds([$userGroupId])
            ->getMany()
            ->toArray();
    }
}
