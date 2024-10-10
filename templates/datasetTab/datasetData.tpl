<span v-if="datasetIsLoading">
    <spinner></spinner>
    {translate key="plugins.generic.dataverse.metadataForm.loadingDataset"}
</span>
<section id="datasetData" class="item datasetData -pkpClearfix">
    <pkp-header>
        <h1>
            {translate key="plugins.generic.dataverse.researchData"}
        </h1>
        <template v-if="!datasetIsLoading" slot="actions">
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
    <tabs label="Dataset data">
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
            <div class="filesList -pkpClearfix">
                <list-panel v-bind="components.datasetFiles" @set="set">
                    <pkp-header slot="header">
                        <h2>{{ components.datasetFiles.title }}</h2>
                        <spinner v-if="components.datasetFiles.isLoading"></spinner>
                        <template v-if="!datasetIsLoading" slot="actions">
                            <pkp-button 
                                ref="fileModalButton"
                                @click="openAddFileModal"
                                :disabled="datasetIsPublished"
                            >
                                {{ components.datasetFiles.addFileLabel }}
                            </pkp-button>
                        </template>
                    </pkp-header>
                    <template v-if="!components.datasetFiles.isLoading" v-slot:item="item">
                        <div class="listPanel__itemSummary">
                            <div class="listPanel__itemIdentity">
                                <div class="listPanel__itemTitle">
                                    <a :href="getFileDownloadUrl(item.item)">
                                        {{ item.item.fileName }}
                                    </a>
                                </div>
                            </div>
                            <div class="listPanel__itemActions">
                                <pkp-button 
                                    @click="openDeleteFileModal(item.item.id)"
                                    class="pkpButton--isWarnable"
                                    :disabled="datasetIsPublished || !canEditPublication"
                                >
                                    {{ __('common.delete') }}
                                </pkp-button>
                            </div>
                        </div>
                    </template>
                </list-panel>
                <modal 
                    v-bind="MODAL_PROPS" 
                    name="fileForm"
                    @opened="checkTermsOfUse"
                >
                    <modal-content 
                        close-label="common.close"
                        modal-name="fileForm"
                        :title="components.datasetFiles.modalTitle"
                    >
                        <pkp-form style="margin: -1rem" v-bind="components.datasetFileForm" @set="set" @success="fileFormSuccess">
                        </pkp-form>
                    </modal-content>
                </modal>
            </div>
        </tab>
    </tabs>
    <modal 
        v-bind="MODAL_PROPS" 
        name="deleteDataset"
    >
        <modal-content 
            close-label="common.close"
            modal-name="deleteDataset"
            :title="deleteDatasetLabel"
        >
            <pkp-form style="margin: -1rem" v-bind="components.deleteDataset" @set="set" @success="location.reload()">
            </pkp-form>
        </modal-content>
    </modal>
</section>