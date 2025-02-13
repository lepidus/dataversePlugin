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
{if $dataverseAdditionalInstructions}
    <div id="dataverseAdditionalInstructions" style="margin-top: 1.5rem;line-height: 1.5rem;font-size: 0.875rem;">
        {$dataverseAdditionalInstructions}
    </div>
{/if}