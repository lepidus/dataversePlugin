<div
    id="dataverseFilesSection"
    v-if="section.type === 'datasetFiles'"
>
    <dataset-files-list-panel
        v-bind="components.datasetFiles"
        @set='set'
    ></dataset-files-list-panel>
    {if $dataverseAdditionalInstructions}
        <div id="dataverseAdditionalInstructions">
            {$dataverseAdditionalInstructions}
        </div>
    {/if}
</div>