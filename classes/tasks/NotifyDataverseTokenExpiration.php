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
        $tokenExpirationDate = '2025-01-17';//$dataverseClient->getDataverseCollectionActions()->getApiTokenExpirationDate();

        if (empty($tokenExpirationDate)) {
            return false;
        }

        $momentsToSendNotification = ['4 weeks', '3 weeks', '2 weeks', '1 week', '1 day'];
        $today = date('Y-m-d');

        foreach ($momentsToSendNotification as $moment) {
            $momentDate = date('Y-m-d', strtotime($tokenExpirationDate . " -$moment"));

            if ($today == $momentDate) {
                $this->sendNotificationEmail();
                break;
            }
        }

        return true;
    }

    private function sendNotificationEmail()
    {
        $context = Application::get()->getRequest()->getContext();
        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            'DATAVERSE_TOKEN_EXPIRATION'
        );

        $email = new Mailable();
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to([['name' => '?', 'email' => '?']]);
        $email->subject($emailTemplate->getLocalizedData('subject'));
        $email->body($emailTemplate->getLocalizedData('body'));

        Mail::send($email);
    }
}
