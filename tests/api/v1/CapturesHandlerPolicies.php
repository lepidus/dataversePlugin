<?php

import('classes.core.Request');
import('classes.journal.Journal');
import('lib.pkp.classes.core.APIRouter');
import('lib.pkp.classes.security.authorization.AuthorizationDecisionManager');

trait CapturesHandlerPolicies
{
    private function getPolicyClassesFor(string $handlerClass, string $operation): array
    {
        $policies = [];

        $route = $this->createMock(\Slim\Route::class);
        $route->method('getName')->willReturn($operation);
        $slimRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $slimRequest->method('getAttribute')->with('route')->willReturn($route);

        $handler = $this->getMockBuilder($handlerClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['addPolicy', 'getSlimRequest'])
            ->getMock();
        $handler->method('getSlimRequest')->willReturn($slimRequest);
        $handler->method('addPolicy')->willReturnCallback(function ($policy) use (&$policies) {
            $policies[] = get_class($policy);
        });
        $handler->_authorizationDecisionManager = $this->createMock(AuthorizationDecisionManager::class);

        $request = $this->createMock(Request::class);
        $request->method('getContext')->willReturn(new Journal());
        $request->method('getRouter')->willReturn($this->createMock(APIRouter::class));

        $args = [];
        $handler->authorize($request, $args, []);

        return $policies;
    }
}
