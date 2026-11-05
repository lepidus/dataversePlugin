<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.security.Role');
import('plugins.generic.dataverse.api.v1.dataverse.DataverseHandler');

class DataverseHandlerRolesTest extends PKPTestCase
{
    private function getRolesByOperation(): array
    {
        $rolesByOperation = [];
        foreach ((new DataverseHandler())->getEndpoints()['GET'] as $endpoint) {
            $rolesByOperation[$endpoint['handler'][1]] = $endpoint['roles'];
        }

        return $rolesByOperation;
    }

    public function testAssistantsOnlyReadTheDataverseName(): void
    {
        $rolesByOperation = $this->getRolesByOperation();

        $this->assertContains(ROLE_ID_ASSISTANT, $rolesByOperation['getDataverseName']);
        $this->assertNotContains(ROLE_ID_ASSISTANT, $rolesByOperation['getRootDataverseName']);
        $this->assertNotContains(ROLE_ID_ASSISTANT, $rolesByOperation['getDataverseLicenses']);
    }
}
