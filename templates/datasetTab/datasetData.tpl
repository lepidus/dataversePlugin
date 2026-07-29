<span v-if="datasetIsLoading && !dataverseIsUnavailable">
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
                id="deleteDatasetButton"
                @click="openDeleteDatasetModal"
                :is-warnable="true"
                :disabled="dataverseIsUnavailable || datasetIsPublished || !canEditPublication"
            >
                {translate key="plugins.generic.dataverse.researchData.delete"}
            </pkp-button>
            {if $canPublish}
                <pkp-button
                    id="disassociateDatasetButton"
                    @click="openDisassociateDatasetModal"
                    :is-warnable="true"
                    :disabled="dataverseIsUnavailable"
                >
                    {translate key="plugins.generic.dataverse.researchData.disassociate"}
                </pkp-button>
                <pkp-button
                    v-if="!datasetIsPublished"
                    id="publishDatasetButton"
                    @click="openPublishDatasetModal"
                    :disabled="dataverseIsUnavailable || datasetIsPublished"
                >
                    {translate key="plugins.generic.dataverse.researchData.publish"}
                </pkp-button>
            {/if}
        </template>
    </pkp-header>
    <div v-if="dataverseIsUnavailable" class="pkp_notification pkp_notification_warning" role="status">
        <p>{{ dataverseErrorMessage }}</p>
        <pkp-button @click="retryDataverseRequests">
            {translate key="plugins.generic.dataverse.error.retry"}
        </pkp-button>
    </div>
    <div id="datasetLabels">
        <span class="datasetLabel datasetLabelDraft" v-if="dataset && !datasetIsPublished">
            {translate key="plugins.generic.dataverse.researchData.label.draft"}
        </span>
        <span class="datasetLabel datasetLabelUnpublished" v-if="dataset && !datasetIsPublished">
            {translate key="plugins.generic.dataverse.researchData.label.unpublished"}
        </span>
        <span class="datasetLabel datasetLabelInReview" v-if="dataset && datasetInReview">
            {translate key="plugins.generic.dataverse.researchData.label.inReview"}
        </span>
    </div>
    <span class="value">
        <p v-html="datasetCitation"></p>
    </span>
    <tabs v-if="!dataverseIsUnavailable" label="Dataset data" :is-side-tabs='true'>
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
    {if $dataverseAdditionalInstructions}
        <div id="dataverseAdditionalInstructions" style="padding: 0 2rem;">
            {$dataverseAdditionalInstructions}
        </div>
    {/if}
</section>
