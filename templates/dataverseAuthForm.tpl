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
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#dataverseAuthForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="dataverseAuthForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" 
	plugin=$pluginName verb="settings" save=true}">
	<div id="description">{translate key="plugins.generic.dataverse.description"}</div>
	{fbvFormArea id="authForm"}
		<div id="authForm">
			{fbvFormSection list=true}
			<label class="label">{fieldLabel name="dvnUri" required="true" key="plugins.generic.dataverse.settings.dvnUri"}</label>
			{fbvElement type="url" id="dvnUri" value=$dvnUri|escape size=$fbvStyles.size.MEDIUM}
			<label class="sub_label">{translate key="plugins.generic.dataverse.settings.dvnUriDescription"}</label>
			
			<label class="label">{fieldLabel name="username" required="true" key="plugins.generic.dataverse.settings.username"}</label>
			{fbvElement type="text" id="username" value=$username|escape size=$fbvStyles.size.MEDIUM}
			<label class="sub_label">{translate key="plugins.generic.dataverse.settings.usernameDescription"}</label>
			
			<label class="label">{fieldLabel name="password" required="true" key="plugins.generic.dataverse.settings.password"}</label>
			{fbvElement type="text" id="password" value=$password|escape size=$fbvStyles.size.MEDIUM}
			<label class="sub_label">{translate key="plugins.generic.dataverse.settings.passwordDescription"}</label>
			{/fbvFormSection}
			{fbvFormButtons}
		</div>
	{/fbvFormArea}
</form>