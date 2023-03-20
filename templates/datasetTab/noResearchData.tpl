<section class="noResearchData">
    <span>{translate key="plugins.generic.dataverse.researchData.noResearchData"}</span>
</section>

<div class="filesList -pkpClearfix">
    <list-panel v-bind="components.datasetFiles" @set="set">
        <pkp-header slot="header">
            <h2>{{ components.datasetFiles.title }}</h2>
            <spinner v-if="components.datasetFiles.isLoading"></spinner>
            <template slot="actions">
                <pkp-button 
                    ref="fileModalButton"
                    @click="openAddFileModal"
                    :disabled="isPosted"
                >
                    {{ components.datasetFiles.addFileLabel }}
                </pkp-button>
            </template>
        </pkp-header>
        <template v-slot:item="item">
            <div class="listPanel__itemSummary">
                <div class="listPanel__itemIdentity">
                    <div class="listPanel__itemTitle">
                        <a :href="item.item.fileDownloadUrl">
                            {{ item.item.fileName }}
                        </a>
                    </div>
                </div>
                <div class="listPanel__itemActions">
                    <pkp-button 
                        @click="openDeleteFileModal(item.item.id)"
                        :disabled="isPosted"
                        class="pkpButton--isWarnable"
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
    <pkp-form v-if="!isFilesEmpty" v-bind="components.datasetMetadata" @set="set"></pkp-form>
</div>
