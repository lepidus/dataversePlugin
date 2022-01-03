<?php
import('classes.handler.Handler');

class TermsOfUseHandler extends Handler
{
	function get($args, $request)
	{
		$contextId = $request->getContext()->getId();
		$plugin = new DataversePlugin();
		$configuration = $plugin->getDataverseConfiguration($contextId);
		$serviceFactory = new DataverseServiceFactory();
		$service = $serviceFactory->build($configuration);
		$termsOfUse = $service->getTermsOfUse();

		return "<html><body>". $termsOfUse . "</body></html>";
	}
}
