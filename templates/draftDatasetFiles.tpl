<dataset-files-list-panel
    v-if="section.type === 'datasetFiles'"
    v-bind="components.datasetFiles"
    @set='set'
></dataset-files-list-panel>