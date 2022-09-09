<script>
	$(function() {ldelim}
		// Attach the upload form handler.
		$('#articleGalleyForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#plupload'),
				uploaderOptions: {ldelim}
					uploadUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT op="updateGalley" function=$function escape=false},
					baseUrl: {$baseUrl|json_encode}
				{rdelim}
			{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="articleGalleyForm" method="post" action="{url op="updateGalley" submissionId=$submissionId publicationId=$publicationId representationId=$representationId}">
	{csrf}
	{fbvFormArea id="galley"}
		{fbvFormSection title="plugins.generic.dataverse.modal.addFile.datasetLabel" required=true}
			{fbvElement type="text" label="plugins.generic.dataverse.modal.addFile.datasetLabelInstructions" value=$label id="label" size=$fbvStyles.size.MEDIUM inline=true required=true}
		{/fbvFormSection}
		{include file="controllers/fileUploadContainer.tpl" id="plupload"}
	{/fbvFormArea}

	{if $supportsDependentFiles}
		{capture assign=dependentFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.dependent.DependentFilesGridHandler" op="fetchGrid" submissionId=$submissionId submissionFile=$articleGalleyFile->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_PRODUCTION escape=false}{/capture}
		{load_url_in_div id="dependentFilesGridDiv" url=$dependentFilesGridUrl}
	{/if}

	{fbvFormButtons submitText="common.save"}
</form>