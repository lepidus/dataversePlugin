{capture assign=draftDatasetFileGridUrl}
    {url 
        router=$smarty.const.ROUTE_COMPONENT 
        component="plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridHandler" 
        op="fetchGrid"
        params=$requestArgs
        escape=false
    }
{/capture}
{load_url_in_div id="draftDatasetFileGridContainer"|uniqid url=$draftDatasetFileGridUrl}
{capture assign=addGalleyLabel}{translate key="submission.layout.galleys"}{/capture}
<label class="description">{translate key="plugins.generic.dataverse.researchDataDescription" addGalleyLabel=$addGalleyLabel}</label>