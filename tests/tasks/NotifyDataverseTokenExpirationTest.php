<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\tasks\NotifyDataverseTokenExpiration;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class NotifyDataverseTokenExpirationTest extends PKPTestCase
{
    public function testRecipientsIncludeSiteAdminsPrimaryContactAndManagersWithoutDuplicates(): void
    {
        $task = (new ReflectionClass(TestableNotifyDataverseTokenExpiration::class))
            ->newInstanceWithoutConstructor();
        $task->usersByRole = [
            Role::ROLE_ID_SITE_ADMIN => [new TokenExpirationRecipientUser('Site Admin', 'admin@example.org')],
            Role::ROLE_ID_MANAGER => [
                new TokenExpirationRecipientUser('Duplicate Admin', 'ADMIN@example.org'),
                new TokenExpirationRecipientUser('Journal Manager', 'manager@example.org'),
            ],
        ];
        $context = new TokenExpirationRecipientContext(42, 'Primary Contact', 'contact@example.org');

        $recipients = $task->getRecipients($context);

        $this->assertSame([
            ['name' => 'Site Admin', 'email' => 'admin@example.org'],
            ['name' => 'Journal Manager', 'email' => 'manager@example.org'],
            ['name' => 'Primary Contact', 'email' => 'contact@example.org'],
        ], $recipients);
        $this->assertSame([
            [Role::ROLE_ID_SITE_ADMIN, Application::CONTEXT_SITE],
            [Role::ROLE_ID_MANAGER, 42],
        ], $task->roleRequests);
    }
}

class TestableNotifyDataverseTokenExpiration extends NotifyDataverseTokenExpiration
{
    public array $usersByRole = [];
    public array $roleRequests = [];

    public function getRecipients($context): array
    {
        return $this->getNotificationRecipients($context);
    }

    protected function getUsersByRole(int $roleId, int $contextId): array
    {
        $this->roleRequests[] = [$roleId, $contextId];
        return $this->usersByRole[$roleId] ?? [];
    }
}

class TokenExpirationRecipientUser
{
    private string $name;
    private string $email;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function getFullName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

class TokenExpirationRecipientContext
{
    private int $id;
    private array $data;

    public function __construct(int $id, string $contactName, string $contactEmail)
    {
        $this->id = $id;
        $this->data = [
            'contactName' => $contactName,
            'contactEmail' => $contactEmail,
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key): string
    {
        return $this->data[$key];
    }
}
