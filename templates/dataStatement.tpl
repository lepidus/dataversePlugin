{fbvFormSection label="plugins.generic.dataverse.dataStatement.title" list=true required=true}
	{translate key="plugins.generic.dataverse.dataStatement.description"}
	{foreach from=$dataStatementTypes key="typeValue" item="typeLabel"}
		{fbvElement type="checkbox" id="dataStatementTypes[]" value=$typeValue checked=false label=$typeLabel translate=false}
	{/foreach}

	{fbvFormSection id="dataStatementUrlsSection" description="plugins.generic.dataverse.dataStatement.repoAvailable.urls.description" label="plugins.generic.dataverse.dataStatement.repoAvailable.urls" required=true}
		{fbvElement type="keyword" id="dataStatementUrls" current=$dataStatementUrls required=true}
	{/fbvFormSection}

	{fbvFormSection id="dataStatementReasonSection" label="plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason" required="true"}
		{fbvElement type="text" label="plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason.description" multilingual="true" id="dataStatementReason" required="true" value=$dataStatementReason maxlength="255"}
	{/fbvFormSection}

	
{/fbvFormSection}

<script type="text/javascript">
	$(function() {ldelim}
		$('input[id^="dataStatement"]').on( "click", function() {ldelim}
			if($(this).val() == {$smarty.const.DATA_STATEMENT_TYPE_REPO_AVAILABLE}) {ldelim}
				if($(this).is(':checked')) {ldelim}
					$('#dataStatementUrlsSection').show();
				{rdelim} else {ldelim}
					$('#dataStatementUrlsSection').hide();
				{rdelim}
			{rdelim} 
		{rdelim});
		$('input[id^="dataStatement"]').on( "click", function() {ldelim}
			if($(this).val() == {$smarty.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE}) {ldelim}
				if($(this).is(':checked')) {ldelim}
					$('#dataStatementReasonSection').show();
				{rdelim} else {ldelim}
					$('#dataStatementReasonSection').hide();
				{rdelim}
			{rdelim} 
		{rdelim});
	{rdelim});
</script>
