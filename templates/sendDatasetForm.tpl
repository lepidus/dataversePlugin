<form class="pkp_form" id="dataverseModalForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
    <div class="pkp_modal_panel" role="dialog" aria-label="Dataverse">
        <div id="descriptionModal" class="header"><p>{translate key="plugins.generic.dataverse.modal.description"}</p></div>
        <div class="content">
            {if !$hideGalleys}
                <ul class="galleys_links">
                    {foreach from=$datasetGalleys item=galley key=genreName}
                        {assign var="label" value=$genreName|cat:" - "|cat:$galley->getLocalizedName()}
                        <li>{fbvElement type="checkbox" label=$label translate=false value=$galley id="galley-item" checked=false}</li>
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
    </div>
<form>
