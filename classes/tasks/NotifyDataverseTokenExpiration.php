<?php

namespace APP\plugins\generic\dataverse\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\mail\Mailable;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use Illuminate\Support\Facades\Mail;

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
        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            'DATAVERSE_TOKEN_EXPIRATION'
        );

        $admin = $this->getAdminUser($context->getId());
        if (!$admin) {
            return;
        }

        $email = new Mailable();
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to([['name' => $admin->getFullName(), 'email' => $admin->getEmail()]]);
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

    private function getAdminUser($contextId)
    {
        $applicationName = Application::get()->getName();
        $adminEnAbbrev = ($applicationName == 'ojs2' ? 'jm' : 'psm');

        $adminUserGroup = $this->getUserGroupByAbbrev($contextId, $adminEnAbbrev);
        if (!$adminUserGroup) {
            return null;
        }

        $adminUsers = Repo::user()->getCollector()
            ->filterByUserGroupIds([$adminUserGroup->getId()])
            ->getMany()
            ->toArray();

        return array_shift($adminUsers);
    }

    private function getUserGroupByAbbrev(int $contextId, string $abbrev)
    {
        $contextUserGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($contextUserGroups as $userGroup) {
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en'));

            if ($userGroupAbbrev === $abbrev) {
                return $userGroup;
            }
        }

        return null;
    }
}
