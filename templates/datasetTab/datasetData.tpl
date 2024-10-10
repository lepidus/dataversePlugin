<span v-if="datasetIsLoading">
    <spinner></spinner>
    {translate key="plugins.generic.dataverse.metadataForm.loadingDataset"}
</span>
<section id="datasetData" class="item datasetData -pkpClearfix">
    <pkp-header>
        <h1>
            {translate key="plugins.generic.dataverse.researchData"}
        </h1>
        <template slot="actions">
            <pkp-button
                @click="openDeleteDatasetModal"
                :is-warnable="true"
                :disabled="datasetIsPublished || !canEditPublication"
            >
                {translate key="plugins.generic.dataverse.researchData.delete"}
            </pkp-button>
            {if $canPublish}
                <pkp-button
                    v-if="!datasetIsPublished"
                    @click="openPublishDatasetModal"
                    :disabled="datasetIsPublished"
                >
                    {translate key="plugins.generic.dataverse.researchData.publish"}
                </pkp-button>
            {/if}
        </template>
    </pkp-header>
    <span class="value">
        <p v-html="datasetCitation"></p>
    </span>
    <tabs label="Dataset data" :is-side-tabs='true'>
        <tab
            id="dataset_metadata"
            label={translate key="plugins.generic.dataverse.researchData.metadata"}
        >
            <pkp-form v-bind="components.datasetMetadata" @set="set"></pkp-form>
        </tab>
        <tab
            id="dataset_files"
            label={translate key="plugins.generic.dataverse.researchData.files"}
        >
            <dataset-files-list-panel
                v-bind="components.datasetFiles"
                @set='set'
            ></dataset-files-list-panel>
        </tab>
    </tabs>
    <modal 
        name="deleteDataset"
        :title="deleteDatasetLabel"
        :closeLabel="__('common.close')"
    >
        <pkp-form style="margin: -1rem" v-bind="components.deleteDataset" @set="set" @success="location.reload()">
        </pkp-form>
    </modal>
</section>