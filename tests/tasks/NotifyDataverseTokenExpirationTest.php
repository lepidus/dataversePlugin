<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\tasks\NotifyDataverseTokenExpiration;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class NotifyDataverseTokenExpirationTest extends PKPTestCase
{
    public function testRecipientsIncludeOnlyDefaultJournalManagersWithoutDuplicates(): void
    {
        $task = (new ReflectionClass(TestableNotifyDataverseTokenExpiration::class))
            ->newInstanceWithoutConstructor();
        $task->usersByRole = [
            Role::ROLE_ID_SITE_ADMIN => [new TokenExpirationRecipientUser('Site Admin', 'admin@example.org')],
        ];
        $task->managerUserGroups = [
            new TokenExpirationRecipientUserGroup(10, true, 'default.groups.name.editor'),
            new TokenExpirationRecipientUserGroup(11, false, 'default.groups.name.manager'),
            new TokenExpirationRecipientUserGroup(12, true, 'default.groups.name.manager'),
        ];
        $task->usersByUserGroup = [
            12 => [
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
        ], $task->roleRequests);
        $this->assertSame([[Role::ROLE_ID_MANAGER, 42]], $task->managerGroupRequests);
        $this->assertSame([[12, 42]], $task->userGroupRequests);
    }
}

class TestableNotifyDataverseTokenExpiration extends NotifyDataverseTokenExpiration
{
    public array $usersByRole = [];
    public array $managerUserGroups = [];
    public array $usersByUserGroup = [];
    public array $roleRequests = [];
    public array $managerGroupRequests = [];
    public array $userGroupRequests = [];

    public function getRecipients($context): array
    {
        return $this->getNotificationRecipients($context);
    }

    protected function getUsersByRole(int $roleId, int $contextId): array
    {
        $this->roleRequests[] = [$roleId, $contextId];
        return $this->usersByRole[$roleId] ?? [];
    }

    protected function getManagerUserGroups(int $contextId): array
    {
        $this->managerGroupRequests[] = [Role::ROLE_ID_MANAGER, $contextId];
        return $this->managerUserGroups;
    }

    protected function getUsersByUserGroup(int $userGroupId, int $contextId): array
    {
        $this->userGroupRequests[] = [$userGroupId, $contextId];
        return $this->usersByUserGroup[$userGroupId] ?? [];
    }
}

class TokenExpirationRecipientUserGroup
{
    private int $id;
    private bool $isDefault;
    private string $nameLocaleKey;

    public function __construct(int $id, bool $isDefault, string $nameLocaleKey)
    {
        $this->id = $id;
        $this->isDefault = $isDefault;
        $this->nameLocaleKey = $nameLocaleKey;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDefault(): bool
    {
        return $this->isDefault;
    }

    public function getData(string $key): string
    {
        return $key === 'nameLocaleKey' ? $this->nameLocaleKey : '';
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
