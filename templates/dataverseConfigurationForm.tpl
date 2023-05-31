{**
 * templates/dataverseConfigurationForm.tpl
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
		$('#dataverseConfigurationForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form
	class="pkp_form"
	id="dataverseConfigurationForm"
	method="POST"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	{csrf}
	<div id="description">
		<p>{translate key="plugins.generic.dataverse.settings.description"}</p>
	</div>
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="translationAndGlossarySettingsFormNotification"}
	{fbvFormArea id="authForm"}
		{fbvFormSection label="plugins.generic.dataverse.settings.dataverseUrl" required=true}
			{fbvElement type="url" id="dataverseUrl" label="plugins.generic.dataverse.settings.dataverseUrlDescription" value=$dataverseUrl|escape size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection label="plugins.generic.dataverse.settings.token" required=true}			
			{fbvElement type="text" password="true" id="apiToken" label="plugins.generic.dataverse.settings.tokenDescription" value=$apiToken|escape size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection label="plugins.generic.dataverse.settings.termsOfUse" required=true}
			{fbvElement type="url" id="termsOfUse" label="plugins.generic.dataverse.settings.termsOfUseDescription" multilingual=true value=$termsOfUse size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormButtons}
	{/fbvFormArea}
</form>