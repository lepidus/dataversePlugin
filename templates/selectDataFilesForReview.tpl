<div id="selectDataFilesForReview">
    {if isset($dataverseError)}
        <p>{$dataverseError}</p>
    {else}
        <p>{translate key="plugins.generic.dataverse.review.selectDataFiles"}</p>

        {foreach from=$datasetFiles item=file}
            <label>
                <input type="checkbox" name="selectDataFilesForReview" value="{$file->getId()}" checked="checked"/>
                {$file->getFileName()}
            </label>
        {/foreach}
    {/if}
</div><br>