<dataset-files-list-panel
    v-if="section.type === 'datasetFiles'"
    v-bind="components.datasetFiles"
    @set='set'
></dataset-files-list-panel>
{if $dataverseAdditionalInstructions}
    <div
        id="dataverseAdditionalInstructions"
        v-if="section.type === 'datasetFiles'"
    >
        {$dataverseAdditionalInstructions}
    </div>
{/if}