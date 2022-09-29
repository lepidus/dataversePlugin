<script type="text/javascript">
    $(function() {ldelim}
        $('#dataverseModalForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="dataverseModalForm" method="post"
    action="{url router=$smarty.const.ROUTE_COMPONENT submissionId=$submissionId save=true}">
    <div id="sendfile" class="pkp_controllers_grid">
        <div class="header">
            <h4>{translate key="plugins.generic.dataverse.modal.addFileTitle"}</h4>
            <div class="pkp_button">
                {include file="linkAction/linkAction.tpl" action=$uploadDatasetFileAction}
            </div>
        </div>
    </div>
    <br>
    <div id="descriptionModal" class="header">
        <p>{translate key="plugins.generic.dataverse.modal.description" dataverseName=$dataverseName}</p>
    </div>
    <div class="content">
        <input type="hidden" name="submissionId" value="{$submissionId|escape}" />
        {if !$hideGalleys}
            <ul class="galleys_links">
                {foreach from=$dataset  item=set}
                    {assign var="label" value=$set[0]|cat:" - "|cat:$set[1]->getLocalizedName()}
                    {if $set[2] == true}{assign var="checked" value=true}{else}{assign var="checked" value=false}{/if}
                    <li>{fbvElement type="checkbox" label=$label translate=false value=$set[1]->getId() id="galleyItems[]" checked=$checked}</li>
                {/foreach}
            </ul>
        {/if}

        {fbvFormSection list="true" title="Dataverse Plugin" translate=false}
        {assign var="publishDataLabel" value={translate key="plugins.generic.dataverse.submissionFileMetadata.publishData" dataverseName=$dataverseName termsOfUseURL=$termsOfUseURL}}
        {fbvElement type="checkbox" label=$publishDataLabel translate=false id="publishData" checked=$checked}
        {/fbvFormSection}

        <div id="datasetButtonContainer">
            {fbvElement type="submit" id="saveDatasetButton" label="common.save" }
        </div>
    </div>
<form>
