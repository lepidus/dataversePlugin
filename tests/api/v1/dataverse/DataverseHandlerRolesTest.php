<?php

use APP\plugins\generic\dataverse\api\v1\dataverse\DataverseHandler;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class DataverseHandlerRolesTest extends PKPTestCase
{
    public function testAssistantsOnlyReadTheDataverseName(): void
    {
        $rolesByOperation = [];
        foreach ((new DataverseHandler())->getEndpoints()['GET'] as $endpoint) {
            $rolesByOperation[$endpoint['handler'][1]] = $endpoint['roles'];
        }

        $this->assertContains(Role::ROLE_ID_ASSISTANT, $rolesByOperation['getDataverseName']);
        $this->assertNotContains(Role::ROLE_ID_ASSISTANT, $rolesByOperation['getRootDataverseName']);
        $this->assertNotContains(Role::ROLE_ID_ASSISTANT, $rolesByOperation['getDataverseLicenses']);
    }
}
