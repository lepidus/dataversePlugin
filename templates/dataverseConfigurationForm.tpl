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
<form class="pkp_form" id="dataverseConfigurationForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" 
plugin=$pluginName verb="settings" save=true}">
{csrf}
	<div id="description">{translate key="plugins.generic.dataverse.description"}</div>
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="translationAndGlossarySettingsFormNotification"}
	{fbvFormArea id="authForm"}
		<div id="authForm">
			{fbvFormSection list=true}
				<label class="label">{fieldLabel name="dataverseUrl" required="true" key="plugins.generic.dataverse.settings.dataverseUrl"}</label>
				{fbvElement type="url" id="dataverseUrl" value=$dataverseUrl|escape size=$fbvStyles.size.MEDIUM}
				<label class="sub_label">{translate key="plugins.generic.dataverse.settings.dataverseUrlDescription"}</label>
				
				<label class="label">{fieldLabel name="apiToken" required="true" key="plugins.generic.dataverse.settings.token"}</label>
				{fbvElement type="text" password="true" id="apiToken" value=$apiToken|escape size=$fbvStyles.size.MEDIUM}
				<label class="sub_label">{translate key="plugins.generic.dataverse.settings.tokenDescription"}</label>

				<label class="label">{fieldLabel name="termsOfUse" required="true" key="plugins.generic.dataverse.settings.termsOfUse"}</label>
				{fbvElement type="url" id="termsOfUse" multilingual=true value=$termsOfUse size=$fbvStyles.size.MEDIUM}
				<label class="sub_label">{translate key="plugins.generic.dataverse.settings.termsOfUseDescription"}</label>
			{/fbvFormSection}
			{fbvFormButtons}
		</div>
	{/fbvFormArea}
</form>