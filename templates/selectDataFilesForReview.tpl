{fbvFormSection id="selectDataFilesForReview" list="true"}
    {if isset($dataverseError)}
        <p>{$dataverseError|escape}</p>
    {else}
        <p>{translate key="plugins.generic.dataverse.review.selectDataFiles"}</p>
        {foreach from=$datasetFiles item=file}
            {fbvElement type="checkbox" id="selectedDataFilesForReview[]" label=$file->getFileName() value=$file->getId() checked="checked" translate=false}
        {/foreach}
    {/if}
{/fbvFormSection}