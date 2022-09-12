<script>
	$(function() {ldelim}
		// Attach the upload form handler.
		$('#articleGalleyForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#plupload'),
				uploaderOptions: {ldelim}
					uploadUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT op="updateDatasetFile" function=$function escape=false},
					baseUrl: {$baseUrl|json_encode}
				{rdelim}
			{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="articleGalleyForm" method="post" action="{url op="saveFile" submissionId=$submissionId publicationId=$publicationId representationId=$representationId}">
	{csrf}
	<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />

	{fbvFormArea id="galley"}
		{fbvFormSection title="plugins.generic.dataverse.modal.addFile.datasetLabel" required=true}
			{fbvElement type="text" label="plugins.generic.dataverse.modal.addFile.datasetLabelInstructions" value=$label id="label" size=$fbvStyles.size.MEDIUM inline=true required=true}
		{/fbvFormSection}
		{include file="controllers/fileUploadContainer.tpl" id="plupload"}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>