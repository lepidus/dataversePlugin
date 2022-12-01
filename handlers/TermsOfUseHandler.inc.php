<?php
import('classes.handler.Handler');

class TermsOfUseHandler extends Handler
{
	function get($args, $request)
	{
		$contextId = $request->getContext()->getId();
		$plugin = new DataversePlugin();
		$dispatcher = new DataverseDispatcher($plugin);
		$configuration = $dispatcher->getDataverseConfiguration($contextId);
		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($configuration);
		$termsOfUse = $service->getTermsOfUse();

		return "<html><body>". htmlentities($termsOfUse) . "</body></html>";
	}
}
