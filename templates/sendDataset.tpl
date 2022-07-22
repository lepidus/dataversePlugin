{**
 * templates/sendDataset.tpl
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#dataverseModalForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<div id="sendDataset" class="pkp_controllers_grid">
	<div class="header">
		<h4>{translate key="plugins.generic.dataverse.dataCitationLabel"}</h4>
		<div class="actions">
			{fbvElement type="button" id="openDatasetModal" label="plugins.generic.dataverse.datasetButton"}
		</div>
	</div>
</div>
<br>

<link rel="stylesheet" type="text/css" href="/plugins/generic/dataverse/styles/datasetModal.css">

<div id="datasetModal" class="pkp_modal pkpModalWrapper" tabIndex="-1">
	<div class="pkp_modal_panel" role="dialog" aria-label="Dataverse">
		<form class="pkp_form" id="dataverseModalForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
			<div id="titleModal" class="header">{translate key="plugins.generic.dataverse.dataCitationLabel"}</div>
			<a id="closeDatasetModal" class="close pkpModalCloseButton">
				<span :aria-hidden="true">×</span>
				<span class="pkp_screen_reader">{translate key="common.closePanel"}</span>
			</a>
			<div id="descriptionModal" class="header"><p>{translate key="plugins.generic.dataverse.modal.description"}</p></div>
			<div class="content">
				{if !$hideGalleys}
					<ul class="galleys_links">
						{foreach from=$dataset  item=set}
							{assign var="label" value=$set[0]|cat:" - "|cat:$set[1]->getLocalizedName()}
							<li>{fbvElement type="checkbox" label=$label translate=false value=$set[1] id="galley-item" checked=false}</li>
						{/foreach}
					</ul>
				{/if}

				{fbvFormSection list="true" title="Dataverse Plugin" translate=false}
					{fbvElement type="checkbox" label="plugins.generic.dataverse.submissionFileMetadata.publishData" id="publishData" checked=false}
				{/fbvFormSection}

				<div id="datasetButtonContainer">
					{fbvElement type="submit" id="saveDatasetButton" label="common.save"}
				</div>
			</div>
		<form>
	</div>
</div>

<script>
	$(function(){ldelim}
		$("#openDatasetModal").click(function(){ldelim}
			$("#datasetModal").addClass("is_visible");
		{rdelim});

		$("#closeDatasetModal").click(function(){ldelim}
			$("#datasetModal").removeClass("is_visible");
		{rdelim});
	{rdelim});
</script>