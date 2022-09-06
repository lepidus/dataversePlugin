<form class="pkp_form" id="articleGalleyForm" method="post" action="{url op="updateGalley" submissionId=$submissionId publicationId=$publicationId representationId=$representationId}">
	{csrf}
	{fbvFormArea id="galley"}
		{fbvFormSection title="submission.layout.galleyLabel" required=true}
			{fbvElement type="text" label="submission.layout.galleyLabelInstructions" value=$label id="label" size=$fbvStyles.size.MEDIUM inline=true required=true}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="select" id="galleyLocale" label="common.language" from=$supportedLocales selected=$galleyLocale|default:$formLocale size=$fbvStyles.size.MEDIUM translate=false inline=true required=true}
		{/fbvFormSection}
		{fbvFormSection for="remotelyHostedContent" list=true}
			{fbvElement type="checkbox" label="submission.layout.galley.remotelyHostedContent" id="remotelyHostedContent"}
			<div id="remote" style="display:none">
				{fbvElement type="text" id="urlRemote" label="submission.layout.galley.remoteURL" value=$urlRemote}
			</div>
		{/fbvFormSection}
		{fbvFormSection id="urlPathSection" title="publication.urlPath"}
			{fbvElement type="text" label="publication.urlPath.description" value=$urlPath id="urlPath" size=$fbvStyles.size.MEDIUM inline=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{if $supportsDependentFiles}
		{capture assign=dependentFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.dependent.DependentFilesGridHandler" op="fetchGrid" submissionId=$submissionId submissionFile=$articleGalleyFile->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_PRODUCTION escape=false}{/capture}
		{load_url_in_div id="dependentFilesGridDiv" url=$dependentFilesGridUrl}
	{/if}

	{fbvFormButtons submitText="common.save"}
</form>