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


{capture assign="termsOfUseDescription"}
    {translate key="plugins.generic.dataverse.termsOfUse.description" params=$termsOfUseArgs}
{/capture}

{fbvFormSection label="plugins.generic.dataverse.termsOfUse.label" list=true}
    {fbvElement type="checkbox" name="termsOfUse" id="termsOfUse" checked=false label=$termsOfUseDescription translate=false}
{/fbvFormSection}