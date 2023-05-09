{fbvFormSection label="plugins.generic.dataverse.dataStatement.title" list=true required=true}
	{translate key="plugins.generic.dataverse.dataStatement.description"}
	{foreach from=$dataStatementTypes key="typeValue" item="typeLabel"}
		{fbvElement type="checkbox" id="dataStatement[]" value=$typeValue checked=false label=$typeLabel translate=false}
	{/foreach}

	{fbvFormSection id="dataStatementUrlsSection" class="data_statement" size=$fbvStyles.size.MEDIUM}
		{fbvElement type="keyword" label="plugins.generic.dataverse.dataStatement.repoAvailable.url" id="dataStatementUrls" required="true" value=$dataStatementUrls maxlength="255"}
	{/fbvFormSection}

	{fbvFormSection id="dataStatementReasonSection" class="data_statement" size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" label="plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason" id="dataStatementReason" required="true" value=$dataStatementReason maxlength="255"}
	{/fbvFormSection}
{/fbvFormSection}