<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#dataverseModalForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="dataverseModalForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT submissionId=$submissionId save=true}">
    <div id="descriptionModal" class="header"><p>{translate key="plugins.generic.dataverse.modal.description" dataverseName=$dataverseName}</p></div>
    <div class="content">
        {if !$hideGalleys}
            <ul class="galleys_links">
                {foreach from=$dataset  item=set}
                    {assign var="label" value=$set[0]|cat:" - "|cat:$set[1]->getLocalizedName()}
                    <li>{fbvElement type="checkbox" label=$label translate=false value=$set[1]->getId() id="galleyItems[]" checked=false}</li>
                {/foreach}
            </ul>
        {/if}

        {fbvFormSection list="true" title="Dataverse Plugin" translate=false}
            {assign var="publishDataLabel" value={translate key="plugins.generic.dataverse.submissionFileMetadata.publishData" dataverseName=$dataverseName termsOfUseURL=$termsOfUseURL}}
            {fbvElement type="checkbox" label=$publishDataLabel translate=false id="publishData" checked=false}
        {/fbvFormSection}

        <div id="datasetButtonContainer">
            {fbvElement type="submit" id="saveDatasetButton" label="common.save"}
        </div>
    </div>
<form>
