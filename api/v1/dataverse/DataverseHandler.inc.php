<?php

import('lib.pkp.classes.handler.APIHandler');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');

class DataverseHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = 'dataverse';
        $roles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR];
        $this->_endpoints = array(
            'GET' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/dataverseName',
                    'handler' => array($this, 'getDataverseName'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/rootDataverseName',
                    'handler' => array($this, 'getRootDataverseName'),
                    'roles' => $roles
                ),
            ),
        );
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.PolicySet');
        $rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

        import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getDataverseName($slimRequest, $response, $args)
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();
            $dataverseName = $dataverseCollection->getName();

            return $response->withJson(['dataverseName' => $dataverseName], 200);
        } catch (DataverseException $e) {
            error_log('Dataverse API error while getting dataverse name: ' . $e->getMessage());
            return $response->withStatus($e->getCode());
        }
    }

    public function getRootDataverseName($slimRequest, $response, $args)
    {
        try {
            $dataverseClient = new DataverseClient();
            $rootDataverseCollection = $dataverseClient->getDataverseCollectionActions()->getRoot();
            $rootDataverseName = $rootDataverseCollection->getName();

            return $response->withJson(['rootDataverseName' => $rootDataverseName], 200);
        } catch (DataverseException $e) {
            error_log('Dataverse API error while getting root name: ' . $e->getMessage());
            return $response->withStatus($e->getCode());
        }
    }
}
