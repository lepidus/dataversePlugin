{fbvFormSection label="plugins.generic.dataverse.researchDataState.title" list=true required=true}
	{translate key="plugins.generic.dataverse.researchDataState.description"}
	{foreach from=$researchDataStates key="stateValue" item="stateLabel"}
		{if $stateValue == 'inManuscript'}{assign var="checked" value=true}{else}{assign var="checked" value=false}{/if}
		{fbvElement type="radio" id="researchData-"|concat:$stateValue name="researchData" value=$stateValue checked=false label=$stateLabel translate=false}
	{/foreach}

	{fbvFormSection id="researchDataUrlSection" class="research_data_states" size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" label="plugins.generic.dataverse.researchDataState.repoAvailable.url" id="researchDataUrl" required="true" value=$reseachDataUrl maxlength="255"}
	{/fbvFormSection}

	{fbvFormSection id="researchDataReasonSection" class="research_data_states" size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" label="plugins.generic.dataverse.researchDataState.private.reason" id="researchDataReason" required="true" value=$researchDataReason maxlength="255"}
	{/fbvFormSection}
{/fbvFormSection}

<script type="text/javascript">
	$(function() {ldelim}
		$('input[id^="researchData"]').on( "click", function() {ldelim}
			let checkedOption = $('input[id^="researchData"]:checked').val();
			if(checkedOption === 'repoAvailable') {ldelim}
				$('.research_data_states').hide();
				$('#researchDataUrlSection').show();
			{rdelim} 
			else if(checkedOption === 'private') {ldelim}
				$('.research_data_states').hide();
				$('#researchDataReasonSection').show();
			{rdelim} 
			else {ldelim}
				$('.research_data_states').hide();
			{rdelim}
		{rdelim});
	{rdelim});
</script>