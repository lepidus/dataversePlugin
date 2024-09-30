<?php

namespace APP\plugins\generic\dataverse\api\v1\dataverse;

use PKP\handler\APIHandler;
use PKP\security\Role;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;

class DataverseHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = 'dataverse';
        $roles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/dataverseName',
                    'handler' => [$this, 'getDataverseName'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/rootDataverseName',
                    'handler' => [$this, 'getRootDataverseName'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/licenses',
                    'handler' => [$this, 'getDataverseLicenses'],
                    'roles' => $roles
                ],
            ],
        ];
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

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

    public function getDataverseLicenses($slimRequest, $response, $args)
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataverseLicenses = $dataverseClient->getDataverseCollectionActions()->getLicenses();

            return $response->withJson(['licenses' => $dataverseLicenses], 200);
        } catch (DataverseException $e) {
            error_log('Dataverse API error while getting licenses: ' . $e->getMessage());
            return $response->withStatus($e->getCode());
        }
    }
}
