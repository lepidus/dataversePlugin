<section class="noResearchData">
    <span>{translate key="plugins.generic.dataverse.researchData.noResearchData"}</span>
</section>

{capture assign=draftDatasetFileGridUrl}
{url 
        router=$smarty.const.ROUTE_COMPONENT 
        component="plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridHandler" 
        op="fetchGrid"
        params=$requestArgs
        escape=false
    }
{/capture}
{load_url_in_div id="draftDatasetFileGridContainer" url=$draftDatasetFileGridUrl}
<pkp-form v-bind="components.datasetMetadata" @set="set"></pkp-form>