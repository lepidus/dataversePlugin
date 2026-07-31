<?php

use PKP\tests\PKPTestCase;
use PKP\security\Role;
use APP\plugins\generic\dataverse\classes\tasks\NotifyDataverseTokenExpiration;

class NotifyDataverseTokenExpirationTest extends PKPTestCase
{
    private function createTestableClass(): TestableNotifyDataverseTokenExpiration
    {
        $task = (new ReflectionClass(TestableNotifyDataverseTokenExpiration::class))
            ->newInstanceWithoutConstructor();
        $task->usersByRole = [
            Role::ROLE_ID_SITE_ADMIN => [new TestableUser('Site Admin', 'admin@example.org')],
        ];
        $task->managerUserGroups = [
            new TestableUserGroup(10, 'default.groups.name.editor'),
            new TestableUserGroup(11, 'default.groups.name.manager'),
        ];
        $task->usersByUserGroup = [
            11 => [
                new TestableUser('Duplicate Admin', 'ADMIN@example.org'),
                new TestableUser('Journal Manager', 'manager@example.org'),
            ],
        ];

        return $task;
    }

    public function testRecipientsIncludeOnlyDefaultJournalManagersWithoutDuplicates(): void
    {
        $task = $this->createTestableClass();
        $context = new TestableContext(42, 'Primary Contact', 'contact@example.org');

        $recipients = $task->getRecipients($context);
        $expectedRecipients = [
            ['name' => 'Site Admin', 'email' => 'admin@example.org'],
            ['name' => 'Journal Manager', 'email' => 'manager@example.org'],
            ['name' => 'Primary Contact', 'email' => 'contact@example.org'],
        ];

        $this->assertEquals($expectedRecipients, $recipients);
    }
}

class TestableNotifyDataverseTokenExpiration extends NotifyDataverseTokenExpiration
{
    public array $usersByRole = [];
    public array $managerUserGroups = [];
    public array $usersByUserGroup = [];

    public function getRecipients($context): array
    {
        return $this->getNotificationRecipients($context);
    }

    protected function getUsersByRole(int $roleId, int $contextId): array
    {
        return $this->usersByRole[$roleId] ?? [];
    }

    protected function getManagerUserGroups(int $contextId): array
    {
        return $this->managerUserGroups;
    }

    protected function getUsersByUserGroup(int $userGroupId, int $contextId): array
    {
        return $this->usersByUserGroup[$userGroupId] ?? [];
    }
}

class TestableUserGroup
{
    private int $id;
    private string $nameLocaleKey;

    public function __construct(int $id, string $nameLocaleKey)
    {
        $this->id = $id;
        $this->nameLocaleKey = $nameLocaleKey;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key): string
    {
        return $key === 'nameLocaleKey' ? $this->nameLocaleKey : '';
    }
}

class TestableUser
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

class TestableContext
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
