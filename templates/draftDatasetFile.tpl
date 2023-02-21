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

{capture assign="termsOfUselabel"}
    {translate key="plugins.generic.dataverse.termsOfUse.description" params=$termsOfUseArgs}
{/capture}

{fbvFormSection label="plugins.generic.dataverse.termsOfUse.label" list=true}
    {fbvElement type="checkbox" name="termsOfUse" id="termsOfUse" checked=false label=$termsOfUselabel translate=false}
{/fbvFormSection}