<template v-if="section.type === 'datasetFiles'">
    <div id="datasetFilesSection">
        {capture assign=draftDatasetFileGridUrl}
            {url 
                router=\PKP\core\PKPApplication::ROUTE_COMPONENT
                component="plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridHandler" 
                op="fetchGrid"
                params=$requestArgs
                escape=false
            }
        {/capture}
        {load_url_in_div id="draftDatasetFileGridContainer"|uniqid url=$draftDatasetFileGridUrl}
    </div>
</template>