{**
 * templates/dataverseAuthForm.tpl
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Dataverse plugin auth form
 *
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#dataverseAuthForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="dataverseAuthForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	<div id="authForm">

		<table width="100%" class="data">
            <tr valign="top">
				<td class="label">{fieldLabel name="dvnUri" required="true" key="plugins.generic.dataverse.settings.dvnUri"}</td>
                <td class="value"><input type="text" name="dvnUri" id="dvnUri" value="{$dvnUri|escape}" size="40" maxlength="90" class="textField"/></td>
			</tr>
            <tr valign="top">
				<td>&nbsp;</td>
				<td>{translate key="plugins.generic.dataverse.settings.dvnUriDescription"}</td>
			</tr>
            <tr valign="top">
                <td class="label">{fieldLabel name="username" required="true" key="plugins.generic.dataverse.settings.username"}</td>
				<td class="value"><input type="text" name="username" id="username" value="{$username|escape}" size="20" maxlength="90" class="textField" /></td>
			</tr>
            <tr valign="top">
				<td>&nbsp;</td>
				<td>{translate key="plugins.generic.dataverse.settings.usernameDescription"}</td>
			</tr>
            <tr valign="top">
				<td class="label">{fieldLabel name="password" required="true" key="plugins.generic.dataverse.settings.password"}</td>
				<td class="value">
					<input type="password" name="password" id="password" value="{$password|escape}" size="20" maxlength="90" class="textField"/>
				</td>
			</tr>
            <tr valign="top">
				<td>&nbsp;</td>
				<td>{translate key="plugins.generic.dataverse.settings.passwordDescription"}</td>
			</tr>
        </table>
        {fbvFormButtons}
        <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	</div>
</form>
