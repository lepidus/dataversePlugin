<?php
/**
 * @file plugins/generic/dataverse/DataversePlugin.inc.php
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataversePlugin
 * @ingroup plugins_generic_dataverse
 *
 * @brief dataverse plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('classes.notification.NotificationManager');
require('plugins/generic/dataversePlugin/libs/swordappv2-php-library/swordappclient.php');

// HTTP status codes
define('DATAVERSE_PLUGIN_HTTP_STATUS_OK',         200);
define('DATAVERSE_PLUGIN_HTTP_STATUS_CREATED',    201);
define('DATAVERSE_PLUGIN_HTTP_STATUS_NO_CONTENT', 204);

class DataversePlugin extends GenericPlugin {

	/**
	 * @see LazyLoadPlugin::register()
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		return $success;
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.dataverse.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.dataverse.description');
	}
	
	/**
	 * @see Plugin::getActions()
	 */
	function getActions($request, $actionArgs) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled() ? array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			) : array(),
			parent::getActions($request, $actionArgs)
		);
	}

	/**
	 * @see Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				$contextId = ($context == null) ? 0 : $context->getId();

				$this->import('classes.form.DataverseAuthForm');
				$form = new DataverseAuthForm($this, $contextId);
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						$notificationManager = new NotificationManager();
						$notificationManager->createTrivialNotification($request->getUser()->getId());
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
					$form->display();
				}
				
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * Wrapper function initializes SWORDv2 client with cURL option to allow
	 * connections to servers with self-signed certificates.
	 * @param $options array
	 * @return SWORDAPPClient
	 */
	function _initSwordClient($options = array(CURLOPT_SSL_VERIFYPEER => FALSE)) {
		if ($httpProxyHost = Config::getVar('proxy', 'http_host')) {
			$options[CURLOPT_PROXY] = $httpProxyHost;
			$options[CURLOPT_PROXYPORT] = Config::getVar('proxy', 'http_port', '80');
			if ($username = Config::getVar('proxy', 'username')) {
				$options[CURLOPT_PROXYUSERPWD] = $username . ':' . Config::getVar('proxy', 'password');
			}
		}
		return new SWORDAPPClient($options);
	}

	/**
	 * Check service document for deprecation warnings returned in requests made
	 * against outdated versions of Dataverse SWORD API.
	 * @param $serviceDocument SWORDAPPServiceDocument Service document
	 * @return string Current API version parsed from deprecation warning
	 */
	function checkAPIVersion($serviceDocument) {
		// Look for current version in deprecation message in warning attribute on workspace		
		$newAPIVersion = '';		
		$pattern = 'current\s+version.+?'. preg_quote('/dvn/api/data-deposit/', '/') .'v(\d+(\.\d+)?)';

		$sd_xml = new SimpleXMLElement($serviceDocument->sac_xml);
		$workspaces = $sd_xml->children('http://www.w3.org/2007/app')->workspace;
		if ($workspaces) {
			foreach ($workspaces[0]->attributes() as $attr => $value) {
				if ($attr == 'warning' && preg_match("/deprecated/i", $value)) {
					if (preg_match("/$pattern/i", $value, $matches)) {
						// New version available
						$newAPIVersion = $matches[1];
						break;
					}
				}
			}
		}		
		return $newAPIVersion;
	}
  

}

?>
